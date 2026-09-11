<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordOpeningInventory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Catalog\CatalogTestCase;

class OpeningInventoryManagementTest extends CatalogTestCase
{
    public function test_index_and_create_display_whole_current_stock_without_decimal_places(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '0.000']);

        $this->actingAs($admin)->get(route('opening-inventory.index'))
            ->assertOk()
            ->assertSee('<td class="px-3 py-3 text-right text-sm">0</td>', false);
        $this->actingAs($admin)->get(route('opening-inventory.create', $variant))
            ->assertOk()
            ->assertSee('<dd class="font-medium">0</dd>', false);
    }

    public function test_positive_whole_opening_is_canonical_atomic_and_actor_bound(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product);
        $unrelated = $this->variant($product, ['size' => 'M10']);

        $this->actingAs($admin)->post(route('opening-inventory.store', $variant), [
            'opening_quantity' => ' 00025.000 ',
            'reason' => "  Opening\n inventory   count  ",
        ])->assertRedirect(route('opening-inventory.index'))->assertSessionHasNoErrors();

        $variant->refresh();
        $movement = StockMovement::query()->sole();
        $this->assertSame('25.000', $variant->current_stock);
        $this->assertSame('0.000', $unrelated->fresh()->current_stock);
        $this->assertSame($variant->id, $movement->product_variant_id);
        $this->assertSame(StockMovement::TYPE_INITIAL_STOCK, $movement->movement_type);
        $this->assertSame('0.000', $movement->quantity_before);
        $this->assertSame('25.000', $movement->quantity_change);
        $this->assertSame('25.000', $movement->quantity_after);
        $this->assertSame($admin->id, $movement->performed_by);
        $this->assertNull($movement->sale_item_id);
        $this->assertNull($movement->restock_item_id);
        $this->assertSame('Opening inventory count', $movement->reason);
        $this->assertNotNull($movement->created_at);
    }

    public function test_positive_fractional_opening_accepts_up_to_three_decimal_places(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'quantity_mode' => 'fractional',
            'unit' => 'kg',
        ]);

        $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload('12.5'))
            ->assertSessionHasNoErrors();

        $this->assertSame('12.500', $variant->fresh()->current_stock);
        $this->assertSame('12.500', StockMovement::query()->sole()->quantity_change);
    }

    public function test_zero_whole_opening_is_recorded_exactly_once(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload('0'))
            ->assertSessionHasNoErrors();
        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)->count());

        $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload('0.000'))
            ->assertSessionHasErrors('opening_quantity');
        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_zero_fractional_opening_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'quantity_mode' => 'fractional',
            'unit' => 'kg',
        ]);

        $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload('0.000'))
            ->assertSessionHasNoErrors();

        $movement = StockMovement::query()->sole();
        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame('0.000', $movement->quantity_before);
        $this->assertSame('0.000', $movement->quantity_change);
        $this->assertSame('0.000', $movement->quantity_after);
    }

    public function test_invalid_quantity_lexical_forms_scale_overflow_and_whole_fraction_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $invalid = ['-1', '+1', '1e3', '1E3', '.5', '1.', '1.0000', 'quantity', ['1'], '100000000000.000', '1.500'];

        foreach ($invalid as $index => $quantity) {
            $product = $this->product($this->category(['name' => 'Validation '.$index]), ['name' => 'Product '.$index]);
            $variant = $this->variant($product, ['size' => 'case-'.$index]);

            $this->actingAs($admin)->post(route('opening-inventory.store', $variant), [
                'opening_quantity' => $quantity,
                'reason' => 'Initial physical count',
            ])->assertSessionHasErrors('opening_quantity');
            $this->assertSame('0.000', $variant->fresh()->current_stock);
        }

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_fractional_quantity_accepts_zero_to_three_supplied_decimal_places(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['0', '0.125', '12.500'] as $index => $quantity) {
            $product = $this->product($this->category(['name' => 'Fractional '.$index]), ['name' => 'Product '.$index]);
            $variant = $this->variant($product, [
                'size' => 'fractional-'.$index,
                'unit' => 'kg',
                'quantity_mode' => 'fractional',
            ]);

            $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload($quantity))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, StockMovement::query()->count());
    }

    public function test_blank_whitespace_and_oversized_reasons_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        foreach (['', " \n\t ", str_repeat('x', 1001)] as $reason) {
            $this->actingAs($admin)->post(route('opening-inventory.store', $variant), [
                'opening_quantity' => '1',
                'reason' => $reason,
            ])->assertSessionHasErrors('reason');
        }

        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_archived_variant_product_and_category_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $archivedVariant = $this->variant($this->product($this->category()), ['status' => ProductVariant::STATUS_ARCHIVED]);
        $archivedProduct = $this->variant($this->product(
            $this->category(['name' => 'Archived product category']),
            ['name' => 'Archived product', 'status' => Product::STATUS_ARCHIVED],
        ));
        $archivedCategory = $this->variant($this->product(
            $this->category(['name' => 'Archived category', 'status' => Category::STATUS_ARCHIVED]),
            ['name' => 'Product under archived category'],
        ));

        foreach ([$archivedVariant, $archivedProduct, $archivedCategory] as $variant) {
            $this->actingAs($admin)->post(route('opening-inventory.store', $variant), $this->payload())
                ->assertSessionHasErrors('product_variant_id');
        }

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_nonzero_stock_and_each_history_type_are_rejected_in_priority_order(): void
    {
        $admin = User::factory()->admin()->create();

        $stockVariant = $this->newNamedVariant('stock', ['current_stock' => '0.001']);
        $saleVariant = $this->newNamedVariant('sale');
        DB::table('sale_items')->insert(['product_variant_id' => $saleVariant->id]);
        $restockVariant = $this->newNamedVariant('restock');
        DB::table('restock_items')->insert(['product_variant_id' => $restockVariant->id]);
        $initialVariant = $this->newNamedVariant('initial', ['current_stock' => '2.000']);
        $this->insertMovement($initialVariant, StockMovement::TYPE_INITIAL_STOCK, $admin);
        $movementVariant = $this->newNamedVariant('movement');
        $this->insertMovement($movementVariant, 'CORRECTION', $admin);

        $this->actingAs($admin)->post(route('opening-inventory.store', $stockVariant), $this->payload())
            ->assertSessionHasErrors('opening_quantity');
        $this->actingAs($admin)->post(route('opening-inventory.store', $saleVariant), $this->payload())
            ->assertSessionHasErrors('opening_quantity');
        $this->actingAs($admin)->post(route('opening-inventory.store', $restockVariant), $this->payload())
            ->assertSessionHasErrors('opening_quantity');
        $this->actingAs($admin)->post(route('opening-inventory.store', $initialVariant), $this->payload())
            ->assertSessionHasErrors(['opening_quantity' => 'Opening inventory has already been recorded for this variant.']);
        $this->actingAs($admin)->post(route('opening-inventory.store', $movementVariant), $this->payload())
            ->assertSessionHasErrors('opening_quantity');

        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_protected_fields_are_prohibited_and_cannot_spoof_variant_actor_or_movement(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $otherVariant = $this->newNamedVariant('other');
        $protected = [
            'product_variant_id' => $otherVariant->id,
            'performed_by' => $otherAdmin->id,
            'movement_type' => 'CORRECTION',
            'quantity_before' => '99.000',
            'quantity_change' => '99.000',
            'quantity_after' => '99.000',
            'sale_item_id' => 1,
            'restock_item_id' => 1,
            'current_stock' => '99.000',
        ];

        $response = $this->actingAs($admin)->post(
            route('opening-inventory.store', $variant),
            array_merge($this->payload('1'), $protected),
        );

        $response->assertSessionHasErrors(array_keys($protected));
        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame('0.000', $otherVariant->fresh()->current_stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_service_revalidates_quantity_mode_and_reason_against_locked_state(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $service = app(RecordOpeningInventory::class);

        foreach ([['1.250', 'Valid reason'], ['1', '   ']] as [$quantity, $reason]) {
            try {
                $service->execute($variant, $admin, $quantity, $reason);
                $this->fail('The authoritative service validation should reject invalid input.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_movement_insert_failure_rolls_back_variant_stock(): void
    {
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
        $variant = $this->variant($this->product($this->category()));
        $missingActor = new User;
        $missingActor->id = 999999;
        $missingActor->role = User::ROLE_ADMIN;
        $missingActor->status = User::STATUS_ACTIVE;

        try {
            app(RecordOpeningInventory::class)->execute(
                $variant,
                $missingActor,
                '3',
                'Initial physical count',
            );
            $this->fail('The movement actor foreign key should reject the insert.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_index_uses_movement_history_for_status_filters_and_hides_ineligible_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $eligible = $this->newNamedVariant('eligible');
        $initializedZero = $this->newNamedVariant('initialized-zero');
        $this->insertMovement($initializedZero, StockMovement::TYPE_INITIAL_STOCK, $admin);
        $inconsistent = $this->newNamedVariant('inconsistent', ['current_stock' => '2.000']);

        $this->actingAs($admin)->get(route('opening-inventory.index', ['initialization' => 'initialized']))
            ->assertOk()
            ->assertSee('initialized-zero')
            ->assertDontSee('>eligible · Grade 8.8<', false);
        $this->actingAs($admin)->get(route('opening-inventory.index', ['initialization' => 'not_initialized']))
            ->assertOk()
            ->assertSee('eligible')
            ->assertSee('inconsistent')
            ->assertSee('Opening inventory unavailable')
            ->assertDontSee('>initialized-zero · Grade 8.8<', false);
        $this->actingAs($admin)->get(route('opening-inventory.create', $inconsistent))->assertStatus(409);
    }

    /** @return array<string, string> */
    private function payload(string $quantity = '1'): array
    {
        return ['opening_quantity' => $quantity, 'reason' => 'Initial physical count'];
    }

    private function newNamedVariant(string $name, array $attributes = []): ProductVariant
    {
        $category = $this->category(['name' => 'Category '.$name]);
        $product = $this->product($category, ['name' => 'Product '.$name]);

        return $this->variant($product, array_merge(['size' => $name], $attributes));
    }

    private function insertMovement(ProductVariant $variant, string $type, User $actor): void
    {
        DB::table('stock_movements')->insert([
            'product_variant_id' => $variant->id,
            'movement_type' => $type,
            'quantity_before' => '0.000',
            'quantity_change' => '0.000',
            'quantity_after' => '0.000',
            'performed_by' => $actor->id,
            'reason' => 'History marker',
            'created_at' => now(),
        ]);
    }
}
