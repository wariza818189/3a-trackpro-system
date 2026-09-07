<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Sales\RecordSale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class PosCheckoutTest extends PosTestCase
{
    public function test_checkout_persists_authoritative_distinct_sale_evidence(): void
    {
        $actor = User::factory()->create();
        $product = $this->product($this->category(), ['name' => 'Claw Hammer']);
        $first = $this->variant($product, ['current_stock' => '10.000', 'selling_price' => '100.00']);
        $second = $this->variant($product, [
            'size' => '1m', 'type_series' => 'Flexible', 'unit' => 'm', 'quantity_mode' => 'fractional',
            'current_stock' => '5.000', 'selling_price' => '12.35',
        ]);
        $this->initialize($first, $actor);
        $this->initialize($second, $actor);
        $token = Str::uuid()->toString();

        $response = $this->actingAs($actor)->post(route('pos.checkout'), [
            'submission_token' => $token,
            'amount_tendered' => '250.00',
            'items' => [
                ['product_variant_id' => $second->id, 'quantity' => '1.25', 'expected_unit_price' => '12.350'],
                ['product_variant_id' => $first->id, 'quantity' => '1', 'expected_unit_price' => '100'],
                ['product_variant_id' => $first->id, 'quantity' => '1.000', 'expected_unit_price' => '100.00'],
            ],
        ]);

        // The preliminary request rejects excess expected-price precision.
        $response->assertSessionHasErrors('items.0.expected_unit_price');
        $response = $this->actingAs($actor)->post(route('pos.checkout'), [
            'submission_token' => $token,
            'amount_tendered' => '250.00',
            'items' => [
                ['product_variant_id' => $second->id, 'quantity' => '1.25', 'expected_unit_price' => '12.35'],
                ['product_variant_id' => $first->id, 'quantity' => '1', 'expected_unit_price' => '100'],
                ['product_variant_id' => $first->id, 'quantity' => '1.000', 'expected_unit_price' => '100.00'],
            ],
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('pos.index'));
        $response->assertSessionHas('sale_confirmation.message', 'Sale completed.');

        $sale = Sale::query()->with(['items.saleMovement'])->sole();
        $this->assertSame($token, $sale->checkout_token);
        $this->assertSame($actor->id, $sale->recorded_by);
        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status);
        $this->assertSame('215.44', $sale->total_amount);
        $this->assertSame('250.00', $sale->cash_received);
        $this->assertSame('34.56', $sale->change_amount);
        $this->assertNull($sale->void_reason);
        $this->assertNull($sale->voided_by);
        $this->assertNull($sale->voided_at);
        $this->assertCount(2, $sale->items);

        $firstItem = $sale->items->firstWhere('product_variant_id', $first->id);
        $this->assertSame('Claw Hammer', $firstItem->product_name_snapshot);
        $this->assertSame('16oz', $firstItem->size_snapshot);
        $this->assertSame('Claw', $firstItem->type_series_snapshot);
        $this->assertSame('', $firstItem->thickness_snapshot);
        $this->assertSame('piece', $firstItem->unit_snapshot);
        $this->assertSame('2.000', $firstItem->quantity);
        $this->assertSame('100.00', $firstItem->unit_price);
        $this->assertSame('200.00', $firstItem->line_total);

        $secondItem = $sale->items->firstWhere('product_variant_id', $second->id);
        $this->assertSame('1.250', $secondItem->quantity);
        $this->assertSame('12.35', $secondItem->unit_price);
        $this->assertSame('15.44', $secondItem->line_total);
        $this->assertSame('8.000', $first->fresh()->current_stock);
        $this->assertSame('3.750', $second->fresh()->current_stock);

        foreach ($sale->items as $item) {
            $movement = $item->saleMovement;
            $this->assertNotNull($movement);
            $this->assertSame(StockMovement::TYPE_SALE, $movement->movement_type);
            $this->assertSame($item->product_variant_id, $movement->product_variant_id);
            $this->assertSame($actor->id, $movement->performed_by);
            $this->assertSame($item->id, $movement->sale_item_id);
            $this->assertNull($movement->restock_item_id);
            $this->assertNull($movement->reason);
            $this->assertSame(bcsub('0.000', $item->quantity, 3), $movement->quantity_change);
            $this->assertSame($movement->quantity_after, bcadd($movement->quantity_before, $movement->quantity_change, 3));
        }
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_pos_finder_and_checkout_enforce_initialization_active_hierarchy_and_stock(): void
    {
        $actor = User::factory()->create();
        $eligible = $this->initializedVariant($actor, ['size' => 'Eligible', 'current_stock' => '2.000']);
        $zero = $this->initializedVariant($actor, ['size' => 'Zero', 'current_stock' => '0.000']);
        $notInitialized = $this->variant($this->product($this->category()), ['size' => 'Not Initialized']);
        $archivedCategory = $this->initializedVariant($actor, ['size' => 'Archived Category Item'], [], ['status' => 'archived']);
        $archivedProduct = $this->initializedVariant($actor, ['size' => 'Archived Product Item'], ['status' => 'archived']);
        $archivedVariant = $this->initializedVariant($actor, ['size' => 'Archived Variant Item', 'status' => 'archived']);

        $this->actingAs($actor)->get(route('pos.index'))
            ->assertOk()->assertSee('Eligible')->assertSee('Zero')->assertSee('Out of stock')
            ->assertDontSee('Not Initialized')->assertDontSee('Archived Category Item')
            ->assertDontSee('Archived Product Item')->assertDontSee('Archived Variant Item');

        foreach ([$notInitialized, $archivedCategory, $archivedProduct, $archivedVariant, $zero] as $variant) {
            try {
                app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [[
                    'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => (string) $variant->selling_price,
                ]]);
                $this->fail('An ineligible item was sold.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        try {
            app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '300', [[
                'product_variant_id' => $eligible->id, 'quantity' => '3', 'expected_unit_price' => '100',
            ]]);
            $this->fail('Insufficient stock was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Current availability is 2.000', $exception->errors()['items.0.quantity'][0]);
        }
        $this->assertSame('2.000', $eligible->fresh()->current_stock);
        $this->assertSame('10.000', $notInitialized->fresh()->current_stock);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_quantity_lexical_range_and_direct_call_types_are_rejected(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor, ['quantity_mode' => 'fractional']);
        foreach (['0', '-1', '+1', '1e2', '1,000', 'words', '.5', '1.', '1.0001', '100000000000.000'] as $quantity) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute(
                $actor, Str::uuid()->toString(), '200', [[
                    'product_variant_id' => $variant->id, 'quantity' => $quantity, 'expected_unit_price' => '100',
                ]],
            ));
        }
        foreach ([['1'], (object) ['value' => '1'], 1.0] as $quantity) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute(
                $actor, Str::uuid()->toString(), '200', [[
                    'product_variant_id' => $variant->id, 'quantity' => $quantity, 'expected_unit_price' => '100',
                ]],
            ));
        }
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '1', 'not-items'));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '1', []));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', array_fill(0, 101, [
            'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100',
        ])));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, 'not-a-uuid', '200', [[
            'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100',
        ]]));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [[
            'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100', 'extra' => true,
        ]]));
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_pos_never_exposes_purchase_cost_to_admin_or_staff(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->initializedVariant($user, ['cost_price' => '73.21', 'selling_price' => '101.23']);
            $this->actingAs($user)->get(route('pos.index'))
                ->assertOk()->assertSee('101.23')->assertDontSee('73.21')->assertDontSee('cost_price');
        }
    }

    public function test_fractional_scales_duplicates_and_original_whole_components(): void
    {
        $actor = User::factory()->create();
        foreach (['1.1', '1.12', '1.123'] as $quantity) {
            $variant = $this->initializedVariant($actor, ['quantity_mode' => 'fractional', 'current_stock' => '10.000']);
            $sale = app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '500', [[
                'product_variant_id' => $variant->id, 'quantity' => $quantity, 'expected_unit_price' => '100',
            ]]);
            $this->assertSame(str_pad($quantity, 5, '0'), $sale->items->sole()->quantity);
        }

        $whole = $this->initializedVariant($actor, ['current_stock' => '5.000']);
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '100', [
            ['product_variant_id' => $whole->id, 'quantity' => '0.5', 'expected_unit_price' => '100'],
            ['product_variant_id' => $whole->id, 'quantity' => '0.5', 'expected_unit_price' => '100.0'],
        ]));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '100', [
            ['product_variant_id' => $whole->id, 'quantity' => '99999999999.999', 'expected_unit_price' => '100'],
            ['product_variant_id' => $whole->id, 'quantity' => '0.001', 'expected_unit_price' => '100'],
        ]));
    }

    public function test_expected_price_validation_staleness_and_refresh_use_current_safe_price(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor, ['quantity_mode' => 'fractional']);
        foreach (['bad', '0', '-1', '+1', '1e2', '1,000', '.5', '1.', '1.001', '10000000000.00'] as $price) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [[
                'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => $price,
            ]]));
        }
        foreach ([['100'], (object) ['value' => '100'], 100.0] as $price) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [[
                'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => $price,
            ]]));
        }
        foreach (['99.99', '100.01'] as $stale) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [[
                'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => $stale,
            ]]));
        }
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '200', [
            ['product_variant_id' => $variant->id, 'quantity' => '0.5', 'expected_unit_price' => '100'],
            ['product_variant_id' => $variant->id, 'quantity' => '0.5', 'expected_unit_price' => '100.01'],
        ]));

        $token = Str::uuid()->toString();
        $response = $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.checkout'), [
            'submission_token' => $token, 'amount_tendered' => '200',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '90']],
        ]);
        $response->assertSessionHasErrors('items.0.expected_unit_price');
        $variant->selling_price = '120.00';
        $variant->save();
        $this->get(route('pos.index'))->assertOk()
            ->assertSee('Prices were refreshed. Review the cart before checking out.')
            ->assertSee('value="120.00" data-pos-price-input', false)
            ->assertDontSee('value="90.00" data-pos-price-input', false)
            ->assertSee('value="'.$token.'"', false)
            ->assertDontSee('70.00');
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_money_payment_rounding_and_overflow_are_exact_and_controlled(): void
    {
        $actor = User::factory()->create();
        $tiny = $this->initializedVariant($actor, [
            'quantity_mode' => 'fractional', 'selling_price' => '0.01', 'current_stock' => '10.000',
        ]);
        try {
            app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '1', [[
                'product_variant_id' => $tiny->id, 'quantity' => '0.001', 'expected_unit_price' => '0.01',
            ]]);
            $this->fail('A zero-rounded line was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The quantity is too small to produce a billable amount at the current price.',
                $exception->errors()['items.0.quantity'][0],
            );
        }
        $positive = app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '1', [[
            'product_variant_id' => $tiny->id, 'quantity' => '0.500', 'expected_unit_price' => '0.01',
        ]]);
        $this->assertSame('0.01', $positive->total_amount);

        $a = $this->initializedVariant($actor, ['quantity_mode' => 'fractional', 'selling_price' => '0.01']);
        $b = $this->initializedVariant($actor, ['quantity_mode' => 'fractional', 'selling_price' => '0.01']);
        $rounded = app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '1', [
            ['product_variant_id' => $a->id, 'quantity' => '0.500', 'expected_unit_price' => '0.01'],
            ['product_variant_id' => $b->id, 'quantity' => '0.500', 'expected_unit_price' => '0.01'],
        ]);
        $this->assertSame('0.02', $rounded->total_amount);

        $normal = $this->initializedVariant($actor);
        foreach (['-1', '+1', '1e2', '1,000', '.5', '1.', '1.001', '100000000000000.00'] as $cash) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), $cash, [[
                'product_variant_id' => $normal->id, 'quantity' => '1', 'expected_unit_price' => '100',
            ]]));
        }
        foreach ([['100'], (object) ['value' => '100'], 100.0] as $cash) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), $cash, [[
                'product_variant_id' => $normal->id, 'quantity' => '1', 'expected_unit_price' => '100',
            ]]));
        }
        foreach (['0', '99.99'] as $underpayment) {
            $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), $underpayment, [[
                'product_variant_id' => $normal->id, 'quantity' => '1', 'expected_unit_price' => '100',
            ]]));
        }
        $exact = app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '100', [[
            'product_variant_id' => $normal->id, 'quantity' => '1', 'expected_unit_price' => '100.0',
        ]]);
        $this->assertSame('0.00', $exact->change_amount);
    }

    public function test_line_and_accumulated_sale_overflow_are_rejected_before_insert(): void
    {
        $actor = User::factory()->create();
        $line = $this->initializedVariant($actor, [
            'quantity_mode' => 'fractional', 'selling_price' => '9999999999.99', 'current_stock' => '99999999999.999',
        ]);
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '99999999999999.99', [[
            'product_variant_id' => $line->id, 'quantity' => '10001', 'expected_unit_price' => '9999999999.99',
        ]]));

        $first = $this->initializedVariant($actor, ['selling_price' => '5000000000.00', 'current_stock' => '20000.000']);
        $second = $this->initializedVariant($actor, ['selling_price' => '5000000000.00', 'current_stock' => '20000.000']);
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '99999999999999.99', [
            ['product_variant_id' => $first->id, 'quantity' => '10000', 'expected_unit_price' => '5000000000'],
            ['product_variant_id' => $second->id, 'quantity' => '10000', 'expected_unit_price' => '5000000000.00'],
        ]));
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_unexpected_and_authoritative_fields_are_strictly_rejected(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor);
        foreach (['recorded_by', 'cashier_id', 'status', 'total', 'subtotal', 'total_amount', 'cash_received', 'change_amount', 'change_due', 'selling_price', 'unit_price', 'line_total', 'cost_price', 'current_stock', 'quantity_before', 'quantity_change', 'quantity_after', 'movement_type', 'performed_by', 'sale_id', 'sale_item_id', 'restock_item_id', 'product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'void_reason', 'voided_by', 'voided_at'] as $field) {
            $payload = $this->payload($variant, ['submission_token' => Str::uuid()->toString()]);
            $payload[$field] = 'tampered';
            $this->actingAs($actor)->post(route('pos.checkout'), $payload)->assertSessionHasErrors('request');

            $itemPayload = $this->payload($variant, ['submission_token' => Str::uuid()->toString()]);
            $itemPayload['items'][0][$field] = 'tampered';
            $this->actingAs($actor)->post(route('pos.checkout'), $itemPayload)->assertSessionHasErrors('items.0');
        }
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_idempotent_replays_compare_canonical_history_not_current_catalog(): void
    {
        $actor = User::factory()->create();
        $otherActor = User::factory()->create();
        $first = $this->initializedVariant($actor, ['current_stock' => '10.000']);
        $second = $this->initializedVariant($actor, ['current_stock' => '10.000', 'selling_price' => '50.00']);
        $token = Str::uuid()->toString();
        $service = app(RecordSale::class);
        $winner = $service->execute($actor, $token, '400', [
            ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50'],
            ['product_variant_id' => $first->id, 'quantity' => '1', 'expected_unit_price' => '100.00'],
            ['product_variant_id' => $first->id, 'quantity' => '2.000', 'expected_unit_price' => '100'],
        ]);
        $replay = $service->execute($actor, $token, '400.00', [
            ['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '100.0'],
            ['product_variant_id' => $second->id, 'quantity' => '2.000', 'expected_unit_price' => '50.00'],
        ]);
        $this->assertSame($winner->id, $replay->id);

        $first->selling_price = '120.00';
        $first->current_stock = '1.000';
        $first->save();
        $first->product->name = 'Renamed after checkout';
        $first->product->save();
        $this->assertSame($winner->id, $service->execute($actor, $token, '400', [
            ['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '100'],
            ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50'],
        ])->id);

        foreach ([
            [$otherActor, '400', [['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '100'], ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50']]],
            [$actor, '401', [['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '100'], ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50']]],
            [$actor, '400', [['product_variant_id' => $first->id, 'quantity' => '2', 'expected_unit_price' => '100'], ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50']]],
            [$actor, '400', [['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '120'], ['product_variant_id' => $second->id, 'quantity' => '2', 'expected_unit_price' => '50']]],
            [$actor, '400', [['product_variant_id' => $first->id, 'quantity' => '3', 'expected_unit_price' => '100']]],
        ] as [$replayActor, $cash, $items]) {
            $this->assertServiceValidation(fn () => $service->execute($replayActor, $token, $cash, $items));
        }
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(2, SaleItem::query()->count());
        $this->assertSame(2, StockMovement::query()->where('movement_type', StockMovement::TYPE_SALE)->count());
    }

    public function test_actor_is_revalidated_before_replay_without_a_user_lock(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor);
        $token = Str::uuid()->toString();
        $items = [['product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100']];
        app(RecordSale::class)->execute($actor, $token, '100', $items);

        $actor->status = 'disabled';
        $actor->save();
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($actor, $token, '100', $items));
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute(new User, $token, '100', $items));
        $missing = new User;
        $missing->id = 999999;
        $missing->exists = true;
        $this->assertServiceValidation(fn () => app(RecordSale::class)->execute($missing, $token, '100', $items));
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_retry_token_ux_retains_ordinary_tokens_but_replaces_semantically_reused_tokens(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor);
        $ordinaryToken = Str::uuid()->toString();
        $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.checkout'), [
            'submission_token' => $ordinaryToken,
            'amount_tendered' => '100',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 'bad', 'expected_unit_price' => '100']],
        ])->assertSessionHasErrors('items.0.quantity');
        $this->get(route('pos.index'))->assertOk()->assertSee('value="'.$ordinaryToken.'"', false);

        $token = Str::uuid()->toString();
        app(RecordSale::class)->execute($actor, $token, '100', [[
            'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100',
        ]]);
        $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.checkout'), [
            'submission_token' => $token,
            'amount_tendered' => '200',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '2', 'expected_unit_price' => '100']],
        ])->assertSessionHasErrors('submission_token');
        $this->get(route('pos.index'))->assertOk()
            ->assertSee('A fresh checkout token was generated. Review the cart before checking out.')
            ->assertDontSee('name="submission_token" value="'.$token.'"', false);

        $this->actingAs($actor)->post(route('pos.checkout'), [
            'submission_token' => $token,
            'amount_tendered' => '100.00',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => '1.000', 'expected_unit_price' => '100.00']],
        ])->assertSessionHas('sale_confirmation.message', 'Sale was already recorded.');
    }

    public function test_unavailable_old_cart_item_is_removed_with_review_notice(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor);
        $token = Str::uuid()->toString();
        $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.checkout'), [
            'submission_token' => $token,
            'amount_tendered' => '100',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 'bad', 'expected_unit_price' => '100']],
        ])->assertSessionHasErrors();
        $variant->status = ProductVariant::STATUS_ARCHIVED;
        $variant->save();

        $this->get(route('pos.index'))->assertOk()
            ->assertSee("Variant #{$variant->id} is no longer available and was removed from the cart.")
            ->assertDontSee('data-pos-cart-row data-id="'.$variant->id.'"', false);
    }

    public function test_second_sale_movement_failure_rolls_back_all_checkout_work(): void
    {
        $actor = User::factory()->create();
        $first = $this->initializedVariant($actor, ['current_stock' => '5.000']);
        $second = $this->initializedVariant($actor, ['current_stock' => '6.000']);
        $unrelated = $this->initializedVariant($actor, ['current_stock' => '9.000']);
        DB::unprepared("CREATE TRIGGER fail_second_sale_movement BEFORE INSERT ON stock_movements WHEN NEW.movement_type = 'SALE' AND (SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'SALE') = 1 BEGIN SELECT RAISE(ABORT, 'forced sale movement failure'); END");

        try {
            app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '500', [
                ['product_variant_id' => $first->id, 'quantity' => '2', 'expected_unit_price' => '100'],
                ['product_variant_id' => $second->id, 'quantity' => '3', 'expected_unit_price' => '100'],
            ]);
            $this->fail('The test trigger did not fail the second movement.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced sale movement failure', $exception->getMessage());
        }
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, SaleItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_SALE)->count());
        $this->assertSame(3, StockMovement::query()->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)->count());
        $this->assertSame('5.000', $first->fresh()->current_stock);
        $this->assertSame('6.000', $second->fresh()->current_stock);
        $this->assertSame('9.000', $unrelated->fresh()->current_stock);
    }

    public function test_sale_and_all_linked_history_are_immutable(): void
    {
        $actor = User::factory()->create();
        $variant = $this->initializedVariant($actor);
        $sale = app(RecordSale::class)->execute($actor, Str::uuid()->toString(), '100', [[
            'product_variant_id' => $variant->id, 'quantity' => '1', 'expected_unit_price' => '100',
        ]]);
        foreach ([$sale, $sale->items->sole(), $sale->items->sole()->saleMovement] as $record) {
            foreach (['save', 'delete'] as $method) {
                try {
                    if ($method === 'save') {
                        $record->setAttribute($record instanceof Sale ? 'status' : 'quantity', $record instanceof Sale ? Sale::STATUS_VOIDED : '2.000');
                    }
                    $record->$method();
                    $this->fail($record::class.' allowed '.$method);
                } catch (LogicException $exception) {
                    $this->assertStringContainsString('Historical records cannot be', $exception->getMessage());
                }
            }
        }
    }

    private function assertServiceValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Defensive sale validation should reject this input.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
