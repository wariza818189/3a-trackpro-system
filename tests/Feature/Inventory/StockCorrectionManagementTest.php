<?php

namespace Tests\Feature\Inventory;

use App\Http\Controllers\StockCorrectionController;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordStockCorrection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class StockCorrectionManagementTest extends StockCorrectionTestCase
{
    public function test_create_form_uses_fresh_stock_and_movement_version_after_a_stale_route_model(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);
        $staleRouteVariant = ProductVariant::query()->findOrFail($variant->id);

        $latestMovement = app(RecordStockCorrection::class)->execute(
            $variant, $admin, '15', $opening->id, 'Intervening verified count',
        );

        $view = app(StockCorrectionController::class)->create($staleRouteVariant);
        $data = $view->getData();

        $this->assertSame($latestMovement->id, $data['latestMovementId']);
        $this->assertSame('15.000', $data['productVariant']->current_stock);
        $this->assertSame($variant->id, $data['productVariant']->id);
        $this->assertNotSame($staleRouteVariant, $data['productVariant']);
    }

    public function test_negative_physical_target_creates_exact_movement_and_changes_quantity_only(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category(), ['name' => 'Claw Hammer']);
        $variant = $this->variant($product, [
            'size' => '16oz',
            'type_series' => 'Claw',
            'thickness' => 'Heavy duty',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'current_stock' => '10.000',
            'cost_price' => '110.00',
            'selling_price' => '150.00',
            'low_stock_threshold' => '2.000',
        ]);
        $unrelated = $this->variant($product, ['size' => 'Unrelated', 'current_stock' => '4.000']);
        $opening = $this->initialize($variant, $admin);
        $this->initialize($unrelated, $admin);

        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
            'corrected_stock' => ' 00007.000 ',
            'reason' => " Physical\n count   discrepancy ",
        ]))->assertSessionHasNoErrors();

        $fresh = $variant->fresh();
        $movement = StockMovement::query()->where('movement_type', StockMovement::TYPE_CORRECTION)->sole();
        $this->assertSame('7.000', $fresh->current_stock);
        $this->assertSame($variant->id, $movement->product_variant_id);
        $this->assertSame(StockMovement::TYPE_CORRECTION, $movement->movement_type);
        $this->assertSame('10.000', $movement->quantity_before);
        $this->assertSame('-3.000', $movement->quantity_change);
        $this->assertSame('7.000', $movement->quantity_after);
        $this->assertSame($admin->id, $movement->performed_by);
        $this->assertSame('Physical count discrepancy', $movement->reason);
        $this->assertNull($movement->sale_item_id);
        $this->assertNull($movement->restock_item_id);
        $this->assertNotNull($movement->created_at);
        $this->assertSame('110.00', $fresh->cost_price);
        $this->assertSame('150.00', $fresh->selling_price);
        $this->assertSame('2.000', $fresh->low_stock_threshold);
        $this->assertSame(['16oz', 'Claw', 'Heavy duty', 'piece', 'whole', ProductVariant::STATUS_ACTIVE], [
            $fresh->size, $fresh->type_series, $fresh->thickness, $fresh->unit, $fresh->quantity_mode, $fresh->status,
        ]);
        $this->assertSame('4.000', $unrelated->fresh()->current_stock);
    }

    public function test_initialized_zero_and_positive_stock_accept_valid_whole_targets_including_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $zero = $this->variant($product, ['size' => 'Zero', 'current_stock' => '0.000']);
        $positive = $this->variant($product, ['size' => 'Positive', 'current_stock' => '5.000']);
        $zeroOpening = $this->initialize($zero, $admin);
        $positiveOpening = $this->initialize($positive, $admin);

        $this->actingAs($admin)->post(route('stock-corrections.store', $zero), $this->payload($zeroOpening, [
            'corrected_stock' => '25.000',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('stock-corrections.store', $positive), $this->payload($positiveOpening, [
            'corrected_stock' => '0',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('25.000', $zero->fresh()->current_stock);
        $this->assertSame('0.000', $positive->fresh()->current_stock);
        $this->assertSame(2, $this->correctionCount());
    }

    public function test_fractional_target_accepts_up_to_three_decimal_places_and_positive_change(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'current_stock' => '2.125',
        ]);
        $opening = $this->initialize($variant, $admin);

        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
            'corrected_stock' => '4.5',
        ]))->assertSessionHasNoErrors();

        $movement = StockMovement::query()->where('movement_type', StockMovement::TYPE_CORRECTION)->sole();
        $this->assertSame('2.125', $movement->quantity_before);
        $this->assertSame('2.375', $movement->quantity_change);
        $this->assertSame('4.500', $movement->quantity_after);
    }

    public function test_missing_opening_history_is_denied_for_zero_and_positive_stock(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['0.000', '7.000'] as $index => $stock) {
            $variant = $this->variant($this->product($this->category(['name' => 'Missing '.$index])), [
                'size' => 'Stock '.$index, 'current_stock' => $stock,
            ]);
            $this->actingAs($admin)->get(route('stock-corrections.create', $variant))->assertStatus(409);
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), [
                'corrected_stock' => '3',
                'expected_movement_id' => '1',
                'reason' => 'Physical count discrepancy',
            ])->assertSessionHasErrors('product_variant_id');
        }

        $this->assertSame(0, $this->correctionCount());
    }

    public function test_archived_category_product_and_variant_are_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $archivedCategory = $this->category(['name' => 'Archived category', 'status' => Category::STATUS_ARCHIVED]);
        $fixtures = [
            $this->variant($this->product($archivedCategory), ['size' => 'Archived category']),
            $this->variant($this->product(
                $this->category(['name' => 'Archived product category']),
                ['status' => Product::STATUS_ARCHIVED],
            ), ['size' => 'Archived product']),
            $this->variant(
                $this->product($this->category(['name' => 'Archived variant category'])),
                ['size' => 'Archived variant', 'status' => ProductVariant::STATUS_ARCHIVED],
            ),
        ];

        foreach ($fixtures as $variant) {
            $opening = $this->initialize($variant, $admin);
            $this->actingAs($admin)->get(route('stock-corrections.create', $variant))->assertStatus(409);
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
                'corrected_stock' => '1',
            ]))->assertSessionHasErrors('product_variant_id');
        }

        $this->assertSame(0, $this->correctionCount());
    }

    public function test_quantity_validation_rejects_invalid_forms_arrays_and_objects(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);

        foreach (['-1', '+1', 'quantity', '1e2', '1,000', '.5', '1.', '1.0000', '1.5', '0.001', '100000000000.000'] as $quantity) {
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
                'corrected_stock' => $quantity,
            ]))->assertSessionHasErrors('corrected_stock');
        }
        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
            'corrected_stock' => ['7'],
        ]))->assertSessionHasErrors('corrected_stock');

        foreach ([['7'], new stdClass] as $quantity) {
            try {
                app(RecordStockCorrection::class)->execute($variant, $admin, $quantity, $opening->id, 'Valid reason');
                $this->fail('The service should reject a non-string corrected stock value.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('corrected_stock', $exception->errors());
            }
        }

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(0, $this->correctionCount());
    }

    public function test_fractional_target_accepts_zero_one_and_three_decimal_places(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['0', '0.001', '2.125'] as $index => $target) {
            $variant = $this->variant($this->product($this->category(['name' => 'Fractional '.$index])), [
                'size' => 'Fractional '.$index,
                'unit' => 'kg',
                'quantity_mode' => 'fractional',
                'current_stock' => '1.000',
            ]);
            $opening = $this->initialize($variant, $admin);
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
                'corrected_stock' => $target,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $this->correctionCount());
    }

    public function test_no_op_is_rejected_before_stale_version(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '7.000']);
        $opening = $this->initialize($variant, $admin);

        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
            'corrected_stock' => '7.000',
            'expected_movement_id' => '999999',
        ]))->assertSessionHasErrors(['corrected_stock' => 'No stock change is required.']);

        $this->assertSame(0, $this->correctionCount());
    }

    public function test_reason_is_required_normalized_limited_and_scalar(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);

        foreach (['', " \n\t ", str_repeat('x', 1001), ['reason']] as $reason) {
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
                'corrected_stock' => '8',
                'reason' => $reason,
            ]))->assertSessionHasErrors('reason');
        }

        try {
            app(RecordStockCorrection::class)->execute($variant, $admin, '8', $opening->id, new stdClass);
            $this->fail('The service should reject a non-string reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
            'corrected_stock' => '8',
            'reason' => str_repeat('x', 1000),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1000, mb_strlen(StockMovement::query()->where('movement_type', StockMovement::TYPE_CORRECTION)->sole()->reason));
    }

    public function test_authoritative_and_catalog_field_tampering_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);
        $protected = [
            'current_stock' => '99.000',
            'quantity_before' => '99.000',
            'quantity_change' => '99.000',
            'quantity_after' => '99.000',
            'movement_type' => StockMovement::TYPE_RESTOCK,
            'performed_by' => $otherAdmin->id,
            'sale_item_id' => 1,
            'restock_item_id' => 1,
            'cost_price' => '0.00',
        ];

        $this->actingAs($admin)->post(
            route('stock-corrections.store', $variant),
            array_merge($this->payload($opening), $protected),
        )->assertSessionHasErrors(array_keys($protected));

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(0, $this->correctionCount());
    }

    public function test_expected_movement_id_validation_and_tampering_fail_safely(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);

        foreach (['', '0', '-1', '1.5', '1e2', 'movement', ['1']] as $expected) {
            $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($opening, [
                'expected_movement_id' => $expected,
            ]))->assertSessionHasErrors('expected_movement_id');
        }

        foreach ([new stdClass, PHP_INT_MAX] as $expected) {
            try {
                app(RecordStockCorrection::class)->execute($variant, $admin, '7', $expected, 'Valid reason');
                $this->fail('The expected movement ID should not permit this correction.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(0, $this->correctionCount());
    }

    public function test_stale_movement_version_rejects_without_overwrite(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);
        $newMovement = app(RecordStockCorrection::class)->execute(
            $variant, $admin, '15', $opening->id, 'Intervening verified count',
        );

        try {
            app(RecordStockCorrection::class)->execute(
                $variant, $admin, '7', $opening->id, 'Old prepared correction',
            );
            $this->fail('The old movement version should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Stock changed while this correction was being prepared. Review the latest stock and try again.',
                $exception->errors()['corrected_stock'][0],
            );
        }

        $this->assertSame('15.000', $variant->fresh()->current_stock);
        $this->assertSame($newMovement->id, $variant->stockMovements()->latest('id')->value('id'));
        $this->assertSame(1, $this->correctionCount());
    }

    public function test_movement_version_rejects_aba_stock_cycle(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);
        $up = app(RecordStockCorrection::class)->execute($variant, $admin, '15', $opening->id, 'First intervening count');
        $back = app(RecordStockCorrection::class)->execute($variant, $admin, '10', $up->id, 'Second intervening count');

        try {
            app(RecordStockCorrection::class)->execute($variant, $admin, '7', $opening->id, 'Old prepared correction');
            $this->fail('The movement ID should detect the ABA stock cycle.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('corrected_stock', $exception->errors());
        }

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame($back->id, $variant->stockMovements()->latest('id')->value('id'));
        $this->assertSame(2, $this->correctionCount());
    }

    public function test_service_rejects_non_admin_disabled_and_missing_persisted_actors(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $disabled = User::factory()->admin()->disabled()->create();
        $missing = new User;
        $missing->id = 999999;
        $missing->role = User::ROLE_ADMIN;
        $missing->status = User::STATUS_ACTIVE;
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);

        foreach ([$staff, $disabled, $missing] as $actor) {
            try {
                app(RecordStockCorrection::class)->execute($variant, $actor, '7', $opening->id, 'Valid reason');
                $this->fail('The service should require a current active Admin.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('actor', $exception->errors());
            }
        }

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(0, $this->correctionCount());
    }

    public function test_correction_history_is_read_only_and_uses_current_catalog_identity(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Inventory Admin']);
        $category = $this->category(['name' => 'Hand Tools']);
        $product = $this->product($category, ['name' => 'Hammer']);
        $variant = $this->variant($product, ['size' => '16oz', 'current_stock' => '5.000']);
        $opening = $this->initialize($variant, $admin);
        app(RecordStockCorrection::class)->execute($variant, $admin, '8', $opening->id, 'Verified count');

        $this->actingAs($admin)->get(route('stock-corrections.index'))
            ->assertOk()
            ->assertSee('Correction history')
            ->assertSee('Hand Tools / Hammer')
            ->assertSee('16oz')
            ->assertSee('+3.000')
            ->assertSee('Inventory Admin')
            ->assertSee('Verified count');
    }

    public function test_movement_insert_failure_rolls_back_stock_and_preserves_initialization(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $opening = $this->initialize($variant, $admin);
        DB::unprepared("CREATE TRIGGER fail_correction_movement BEFORE INSERT ON stock_movements WHEN NEW.movement_type = 'CORRECTION' BEGIN SELECT RAISE(ABORT, 'forced correction movement failure'); END");

        try {
            app(RecordStockCorrection::class)->execute($variant, $admin, '7', $opening->id, 'Valid correction');
            $this->fail('The CORRECTION movement should have failed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced correction movement failure', $exception->getMessage());
        }

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)->count());
        $this->assertSame(0, $this->correctionCount());
    }
}
