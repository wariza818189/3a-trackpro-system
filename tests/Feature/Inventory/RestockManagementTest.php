<?php

namespace Tests\Feature\Inventory;

use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordRestock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestockManagementTest extends RestockTestCase
{
    public function test_initialized_zero_stock_records_exact_multi_item_history_and_latest_cost(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category(), ['name' => 'Machine Bolt']);
        $whole = $this->variant($product, ['size' => 'M8', 'current_stock' => '0.000', 'cost_price' => '80.00']);
        $fractional = $this->variant($product, [
            'size' => 'Bulk', 'unit' => 'kg', 'quantity_mode' => 'fractional',
            'current_stock' => '2.125', 'cost_price' => '40.00',
        ]);
        $unrelated = $this->variant($product, ['size' => 'Unrelated', 'current_stock' => '9.000']);
        $this->initialize($whole, $actor);
        $this->initialize($fractional, $actor);
        $this->initialize($unrelated, $actor);

        $payload = $this->payload($whole, ['items' => [
            ['product_variant_id' => $whole->id, 'quantity' => '5', 'unit_cost' => '110'],
            ['product_variant_id' => $fractional->id, 'quantity' => '0.125', 'unit_cost' => '12.50'],
        ]]);

        $this->actingAs($actor)->post(route('stock-in.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $restock = Restock::query()->with('items.stockMovement')->sole();
        $this->assertSame($actor->id, $restock->recorded_by);
        $this->assertSame('551.56', $restock->total_cost);
        $this->assertCount(2, $restock->items);
        $this->assertSame('5.000', $whole->fresh()->current_stock);
        $this->assertSame('110.00', $whole->fresh()->cost_price);
        $this->assertSame('2.250', $fractional->fresh()->current_stock);
        $this->assertSame('12.50', $fractional->fresh()->cost_price);
        $this->assertSame('9.000', $unrelated->fresh()->current_stock);

        foreach ($restock->items as $item) {
            $this->assertSame('Machine Bolt', $item->product_name_snapshot);
            $this->assertNotNull($item->stockMovement);
            $this->assertSame(StockMovement::TYPE_RESTOCK, $item->stockMovement->movement_type);
            $this->assertSame($actor->id, $item->stockMovement->performed_by);
            $this->assertSame($item->product_variant_id, $item->stockMovement->product_variant_id);
            $this->assertSame($item->quantity, $item->stockMovement->quantity_change);
            $this->assertNull($item->stockMovement->sale_item_id);
            $this->assertNull($item->stockMovement->reason);
            $this->assertSame($item->variant->current_stock, $item->stockMovement->quantity_after);
        }
        $this->assertSame(2, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_initialized_positive_stock_is_allowed_with_exact_before_and_after(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $this->initialize($variant, $actor);

        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '5.000', 'unit_cost' => '110.00']],
        ]))->assertSessionHasNoErrors();

        $movement = StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->sole();
        $this->assertSame('10.000', $movement->quantity_before);
        $this->assertSame('5.000', $movement->quantity_change);
        $this->assertSame('15.000', $movement->quantity_after);
        $this->assertSame('15.000', $variant->fresh()->current_stock);

        $firstItem = RestockItem::query()->sole();
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '120']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('110.00', $firstItem->fresh()->unit_cost);
        $this->assertSame('120.00', $variant->fresh()->cost_price);
    }

    public function test_missing_opening_history_is_denied_even_with_zero_or_positive_stock(): void
    {
        $actor = User::factory()->create();
        foreach (['0.000', '7.000'] as $stock) {
            $variant = $this->variant($this->product($this->category()), ['size' => 'Stock '.$stock, 'current_stock' => $stock]);
            $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant))
                ->assertSessionHasErrors('items.0.product_variant_id');
        }

        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
    }

    public function test_archived_category_product_and_variant_are_denied(): void
    {
        $actor = User::factory()->create();
        $fixtures = [];
        $archivedCategory = $this->category(['status' => 'archived']);
        $fixtures[] = $this->variant($this->product($archivedCategory), ['size' => 'Archived category']);
        $category = $this->category();
        $fixtures[] = $this->variant($this->product($category, ['status' => 'archived']), ['size' => 'Archived product']);
        $fixtures[] = $this->variant($this->product($this->category()), ['size' => 'Archived variant', 'status' => 'archived']);

        foreach ($fixtures as $variant) {
            $this->initialize($variant, $actor);
            $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant))
                ->assertSessionHasErrors('items.0.product_variant_id');
        }
        $this->assertSame(0, Restock::query()->count());
    }

    public function test_quantity_validation_rejects_invalid_forms_and_accepts_valid_modes(): void
    {
        $actor = User::factory()->create();
        $whole = $this->variant($this->product($this->category()), ['size' => 'Whole']);
        $fractional = $this->variant($this->product($this->category()), [
            'size' => 'Fractional', 'quantity_mode' => 'fractional', 'unit' => 'kg',
        ]);
        $this->initialize($whole, $actor);
        $this->initialize($fractional, $actor);

        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($whole, [
            'items' => [['product_variant_id' => $whole->id, 'quantity' => '25.000', 'unit_cost' => '1']],
        ]))->assertSessionHasNoErrors();
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($fractional, [
            'items' => [['product_variant_id' => $fractional->id, 'quantity' => '0.125', 'unit_cost' => '1']],
        ]))->assertSessionHasNoErrors();

        foreach (['0', '-1', 'word', '1e3', '+1', '1.0000', '1.5', '100000000000.000'] as $quantity) {
            $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($whole, [
                'items' => [['product_variant_id' => $whole->id, 'quantity' => $quantity, 'unit_cost' => '1']],
            ]))->assertSessionHasErrors('items.0.quantity');
        }

        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($fractional, [
            'items' => [['product_variant_id' => $fractional->id, 'quantity' => '99999999999.999', 'unit_cost' => '0']],
        ]))->assertSessionHasErrors('items.0.quantity');
    }

    public function test_cost_validation_accepts_zero_and_rejects_invalid_forms_and_overflow(): void
    {
        $actor = User::factory()->create();
        $variant = $this->variant($this->product($this->category()), ['size' => 'Free stock']);
        $this->initialize($variant, $actor);

        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '0']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('0.00', RestockItem::query()->sole()->unit_cost);
        $this->assertSame('0.00', $variant->fresh()->cost_price);

        foreach (['cost', '-1', '1e3', '+1', '1.001', '10000000000.00'] as $cost) {
            $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => $cost]],
            ]))->assertSessionHasErrors('items.0.unit_cost');
        }
    }

    public function test_totals_use_exact_half_up_rounding_and_reject_line_and_header_overflow(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category());
        $first = $this->variant($product, ['size' => 'First', 'quantity_mode' => 'fractional', 'unit' => 'kg']);
        $second = $this->variant($product, ['size' => 'Second', 'quantity_mode' => 'fractional', 'unit' => 'm']);
        $this->initialize($first, $actor);
        $this->initialize($second, $actor);

        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($first, ['items' => [
            ['product_variant_id' => $first->id, 'quantity' => '0.001', 'unit_cost' => '5.00'],
            ['product_variant_id' => $second->id, 'quantity' => '0.333', 'unit_cost' => '10.00'],
        ]]))->assertSessionHasNoErrors();
        $restock = Restock::query()->with('items')->sole();
        $this->assertSame(['0.01', '3.33'], $restock->items->pluck('line_total')->all());
        $this->assertSame('3.34', $restock->total_cost);

        $lineOverflow = $this->variant($product, ['size' => 'Line overflow', 'quantity_mode' => 'fractional', 'unit' => 'roll']);
        $this->initialize($lineOverflow, $actor);
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($lineOverflow, ['items' => [[
            'product_variant_id' => $lineOverflow->id, 'quantity' => '99999999999.999', 'unit_cost' => '1001.00',
        ]]]))->assertSessionHasErrors('items.0.unit_cost');

        $third = $this->variant($product, ['size' => 'Header A', 'quantity_mode' => 'fractional', 'unit' => 'sheet']);
        $fourth = $this->variant($product, ['size' => 'Header B', 'quantity_mode' => 'fractional', 'unit' => 'piece']);
        $this->initialize($third, $actor);
        $this->initialize($fourth, $actor);
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($third, ['items' => [
            ['product_variant_id' => $third->id, 'quantity' => '99999999999.999', 'unit_cost' => '1000.00'],
            ['product_variant_id' => $fourth->id, 'quantity' => '99999999999.999', 'unit_cost' => '1000.00'],
        ]]))->assertSessionHasErrors('items');
    }

    public function test_historical_cost_and_snapshots_survive_later_catalog_changes_and_replay(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category(), ['name' => 'Original Product']);
        $variant = $this->variant($product, ['size' => 'Original Size', 'cost_price' => '20.00']);
        $this->initialize($variant, $actor);
        $payload = $this->payload($variant, ['submission_token' => Str::uuid()->toString()]);

        $this->actingAs($actor)->post(route('stock-in.store'), $payload)->assertSessionHasNoErrors();
        $restockId = Restock::query()->sole()->id;
        $itemId = RestockItem::query()->sole()->id;
        $stock = $variant->fresh()->current_stock;

        $product->name = 'Renamed Product';
        $product->save();
        $variant->selling_price = '99.00';
        $variant->save();

        $this->actingAs($actor)->post(route('stock-in.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($restockId, Restock::query()->sole()->id);
        $item = RestockItem::query()->sole();
        $this->assertSame($itemId, $item->id);
        $this->assertSame('Original Product', $item->product_name_snapshot);
        $this->assertSame('Original Size', $item->size_snapshot);
        $this->assertSame('110.00', $item->unit_cost);
        $this->assertSame($stock, $variant->fresh()->current_stock);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_equivalent_replay_is_canonical_and_order_independent(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category());
        $first = $this->variant($product, ['size' => 'Replay whole']);
        $second = $this->variant($product, ['size' => 'Replay fractional', 'quantity_mode' => 'fractional', 'unit' => 'kg']);
        $this->initialize($first, $actor);
        $this->initialize($second, $actor);
        $token = Str::uuid()->toString();
        $original = [
            'submission_token' => $token,
            'reference_text' => ' Delivery   10 ',
            'notes' => " Received\n complete ",
            'items' => [
                ['product_variant_id' => $first->id, 'quantity' => '5', 'unit_cost' => '10'],
                ['product_variant_id' => $second->id, 'quantity' => '0.5', 'unit_cost' => '2.5'],
            ],
        ];
        $equivalent = [
            'submission_token' => strtoupper($token),
            'reference_text' => 'Delivery 10',
            'notes' => 'Received complete',
            'items' => [
                ['product_variant_id' => $second->id, 'quantity' => '0.500', 'unit_cost' => '2.50'],
                ['product_variant_id' => $first->id, 'quantity' => '5.000', 'unit_cost' => '10.00'],
            ],
        ];

        $this->actingAs($actor)->post(route('stock-in.store'), $original)->assertSessionHasNoErrors();
        $stock = [$first->fresh()->current_stock, $second->fresh()->current_stock];
        $this->actingAs($actor)->post(route('stock-in.store'), $equivalent)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Stock In was already recorded.');

        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(2, RestockItem::query()->count());
        $this->assertSame(2, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame($stock, [$first->fresh()->current_stock, $second->fresh()->current_stock]);
    }

    public function test_token_reuse_with_any_changed_semantics_or_actor_is_rejected_safely(): void
    {
        $actor = User::factory()->create();
        $otherActor = User::factory()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product, ['size' => 'Original']);
        $otherVariant = $this->variant($product, ['size' => 'Other']);
        $this->initialize($variant, $actor);
        $this->initialize($otherVariant, $actor);
        $payload = $this->payload($variant);
        $this->actingAs($actor)->post(route('stock-in.store'), $payload)->assertSessionHasNoErrors();

        $changes = [
            ['reference_text' => 'different'],
            ['notes' => 'different'],
            ['items' => [['product_variant_id' => $otherVariant->id, 'quantity' => '5', 'unit_cost' => '110']]],
            ['items' => [['product_variant_id' => $variant->id, 'quantity' => '6', 'unit_cost' => '110']]],
            ['items' => [['product_variant_id' => $variant->id, 'quantity' => '5', 'unit_cost' => '111']]],
        ];
        foreach ($changes as $change) {
            $this->actingAs($actor)->post(route('stock-in.store'), array_replace($payload, $change))
                ->assertSessionHasErrors(['submission_token' => 'This Stock In submission token cannot be reused.']);
        }
        $this->actingAs($otherActor)->post(route('stock-in.store'), $payload)
            ->assertSessionHasErrors(['submission_token' => 'This Stock In submission token cannot be reused.']);

        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_header_normalization_limits_token_retention_and_duplicate_items(): void
    {
        $actor = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        $blank = $this->payload($variant, ['reference_text' => " \n ", 'notes' => "\t"]);
        $this->actingAs($actor)->post(route('stock-in.store'), $blank)->assertSessionHasNoErrors();
        $restock = Restock::query()->sole();
        $this->assertNull($restock->reference_text);
        $this->assertNull($restock->notes);

        $token = Str::uuid()->toString();
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'submission_token' => $token,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '0', 'unit_cost' => '1']],
        ]))->assertSessionHasErrors('items.0.quantity')->assertSessionHasInput('submission_token', $token);
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'reference_text' => str_repeat('x', 1001),
            'notes' => str_repeat('y', 1001),
        ]))->assertSessionHasErrors(['reference_text', 'notes']);
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, [
            'submission_token' => 'not-a-token',
        ]))->assertSessionHasErrors('submission_token');
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, ['items' => [
            ['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1'],
            ['product_variant_id' => $variant->id, 'quantity' => '2', 'unit_cost' => '1'],
        ]]))->assertSessionHasErrors('items.1.product_variant_id');

        $tooMany = array_fill(0, 101, ['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1']);
        $this->actingAs($actor)->post(route('stock-in.store'), $this->payload($variant, ['items' => $tooMany]))
            ->assertSessionHasErrors('items');
    }

    public function test_tampered_fields_and_unexpected_item_keys_are_rejected_without_mutation(): void
    {
        $actor = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        $payload = $this->payload($variant);
        $payload += ['recorded_by' => 999, 'total_cost' => '0.00', 'restock_id' => 10, 'created_at' => now()];
        $payload['items'][0] += [
            'restock_item_id' => 1, 'restock_id' => 1, 'product_name_snapshot' => 'Spoof',
            'size_snapshot' => 'Spoof', 'type_series_snapshot' => 'Spoof', 'thickness_snapshot' => 'Spoof',
            'unit_snapshot' => 'kg', 'line_total' => '0.00', 'current_stock' => '999.000',
            'cost_price' => '0.00', 'quantity_before' => '0.000', 'quantity_change' => '999.000',
            'quantity_after' => '999.000', 'performed_by' => 999, 'movement_type' => 'CORRECTION',
            'sale_item_id' => 1, 'unexpected' => 'field',
        ];

        $this->actingAs($actor)->post(route('stock-in.store'), $payload)->assertSessionHasErrors('items.0');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame('0.000', $variant->fresh()->current_stock);
        $this->assertSame('50.00', $variant->fresh()->cost_price);
    }

    public function test_second_movement_failure_rolls_back_entire_multi_item_receipt(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category());
        $first = $this->variant($product, ['size' => 'Atomic A', 'current_stock' => '2.000', 'cost_price' => '20.00']);
        $second = $this->variant($product, ['size' => 'Atomic B', 'current_stock' => '3.000', 'cost_price' => '30.00']);
        $this->initialize($first, $actor);
        $this->initialize($second, $actor);
        DB::unprepared("CREATE TRIGGER fail_second_restock_movement BEFORE INSERT ON stock_movements WHEN NEW.movement_type = 'RESTOCK' AND (SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'RESTOCK') = 1 BEGIN SELECT RAISE(ABORT, 'forced second movement failure'); END");

        try {
            app(RecordRestock::class)->execute(
                $actor,
                Str::uuid()->toString(),
                null,
                null,
                [
                    ['product_variant_id' => $first->id, 'quantity' => '1', 'unit_cost' => '21'],
                    ['product_variant_id' => $second->id, 'quantity' => '1', 'unit_cost' => '31'],
                ],
            );
            $this->fail('The second RESTOCK movement should have failed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced second movement failure', $exception->getMessage());
        }

        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame(['2.000', '20.00'], [$first->fresh()->current_stock, $first->fresh()->cost_price]);
        $this->assertSame(['3.000', '30.00'], [$second->fresh()->current_stock, $second->fresh()->cost_price]);
    }

    public function test_staff_never_receives_existing_or_historical_cost_values_but_admin_does(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['cost_price' => '9182.43']);
        $this->initialize($variant, $staff);

        $create = $this->actingAs($staff)->get(route('stock-in.create'))->assertOk();
        $create->assertSee('Unit purchase cost')->assertDontSee('9182.43');
        $this->actingAs($staff)->post(route('stock-in.store'), $this->payload($variant, ['items' => [[
            'product_variant_id' => $variant->id, 'quantity' => '2', 'unit_cost' => '7321.09',
        ]]]))->assertSessionHasNoErrors();
        $restock = Restock::query()->sole();

        $this->actingAs($staff)->get(route('product-variants.index'))->assertDontSee('7321.09');
        $this->actingAs($staff)->get(route('stock-in.index'))->assertDontSee('14642.18')->assertDontSee('7321.09');
        $this->actingAs($staff)->get(route('stock-in.show', $restock->id))->assertDontSee('14642.18')->assertDontSee('7321.09');

        $this->actingAs($admin)->get(route('stock-in.index'))->assertSee('14642.18');
        $this->actingAs($admin)->get(route('stock-in.show', $restock->id))->assertSee('7321.09')->assertSee('14642.18');
    }

    public function test_service_defensively_rejects_invalid_header_and_item_input(): void
    {
        $actor = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);

        foreach ([
            ['bad-token', null, null, [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1']]],
            [Str::uuid()->toString(), [], null, [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1']]],
            [Str::uuid()->toString(), null, str_repeat('x', 1001), [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1']]],
            [Str::uuid()->toString(), null, null, [['product_variant_id' => $variant->id, 'quantity' => '1', 'unit_cost' => '1', 'extra' => 1]]],
        ] as [$token, $reference, $notes, $items]) {
            try {
                app(RecordRestock::class)->execute($actor, $token, $reference, $notes, $items);
                $this->fail('Defensive service validation should reject invalid input.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, Restock::query()->count());
    }
}
