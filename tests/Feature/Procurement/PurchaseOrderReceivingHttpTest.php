<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockDamageItem;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Inventory\RestockTestCase;

final class PurchaseOrderReceivingHttpTest extends RestockTestCase
{
    public function test_admin_and_staff_can_browse_and_receive_while_guests_disabled_and_staff_mutations_are_denied(): void
    {
        [$admin, $staff, $purchaseOrder, $item] = $this->fixture();
        $this->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertRedirect('/login');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertRedirect('/login');

        $disabled = User::factory()->disabled()->create();
        $this->actingAs($disabled)->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->actingAs($disabled)->get(route('purchase-orders.receive.create', $purchaseOrder))->assertRedirect('/login');

        $unsupported = User::factory()->create();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('users')->where('id', $unsupported->id)->update(['role' => 'owner']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        $unsupported->refresh();
        $this->actingAs($unsupported)->get(route('purchase-orders.index'))->assertForbidden();
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertForbidden();
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertForbidden();
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '1', '70'))
            ->assertForbidden();

        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertOk()->assertDontSee('data-po-create-action');
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()->assertSee('data-po-receive')->assertDontSee('data-po-edit');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertSee('data-receive-token');
        $this->get(route('purchase-orders.create'))->assertForbidden();
        $this->post(route('purchase-orders.store'), [])->assertForbidden();
        $this->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
        $this->patch(route('purchase-orders.update', $purchaseOrder), [])->assertForbidden();

        $this->actingAs($admin)->get(route('purchase-orders.index'))->assertOk()->assertSee('data-po-create-action');
        $this->get(route('purchase-orders.create'))->assertOk();
        $this->get(route('purchase-orders.edit', $purchaseOrder))->assertOk();
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertSee((string) $item->id);
    }

    public function test_form_displays_outstanding_lines_and_preserves_token_and_input_after_validation_error(): void
    {
        [, $staff, $purchaseOrder, $item, , $otherItem] = $this->fixture(twoLines: true);
        $form = $this->actingAs($staff)->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk();
        $form->assertSee('Ordered')->assertSee('Accepted')->assertSee('Outstanding')
            ->assertSee('5.000 piece')->assertSee('Actual unit cost');
        $this->assertMatchesRegularExpression('/name="submission_token" value="[0-9a-f-]{36}"/', $form->getContent());

        $token = Str::uuid()->toString();
        $this->from(route('purchase-orders.receive.create', $purchaseOrder))
            ->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '1', '-1', $token))
            ->assertRedirect(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertSessionHasErrors('items.0.actual_unit_cost');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertOk()->assertSee('value="'.$token.'"', false)->assertSee('value="1"', false);

        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($otherItem, '2', '31.25'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $form = $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk();
        $form->assertSee('data-receive-line="'.$item->id.'"', false)
            ->assertDontSee('data-receive-line="'.$otherItem->id.'"', false);
    }

    public function test_partial_and_full_http_receipts_show_history_and_redact_staff_costs(): void
    {
        [$admin, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $purchaseOrder),
            $this->receiptPayload($item, '2', '73.45', reference: 'DR-26C'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame('2.000', $variant->fresh()->current_stock);
        $staffHtml = $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('3.000 piece')->assertSee('DR-26C')->assertSee('Receipt history')->getContent();
        $this->assertStringNotContainsString('91.27', $staffHtml);
        $this->assertStringNotContainsString('73.45', $staffHtml);
        $this->assertStringNotContainsString('146.90', $staffHtml);
        $this->assertStringNotContainsString('Expected unit cost', $staffHtml);
        $this->assertStringNotContainsString('Actual unit cost:', $staffHtml);
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertDontSee('91.27');
        $this->get(route('purchase-orders.index'))->assertOk()->assertDontSee('91.27')->assertDontSee('73.45');

        $this->actingAs($admin)->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('₱91.27')->assertSee('₱73.45')->assertSee('₱146.90');
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '3', '74.00'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->fresh()->status);
        $this->assertSame('5.000', $variant->fresh()->current_stock);
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('0.000 piece')->assertDontSee('data-po-receive');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertStatus(409);
    }

    public function test_full_post_replays_after_completion_and_conflicting_token_has_no_second_mutation(): void
    {
        [, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $payload = $this->receiptPayload($item, '5', '73.45');
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $purchaseOrder), $payload)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $payload)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHas('success', 'This receipt was already recorded.');
        $conflict = $this->receiptPayload($item, '4', '73.45', $payload['submission_token']);
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $conflict)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('submission_token');
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('5.000', $variant->fresh()->current_stock);
    }

    public function test_over_receipt_and_empty_delivery_report_errors_without_mutation(): void
    {
        [, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $this->actingAs($staff)->from(route('purchase-orders.receive.create', $purchaseOrder))
            ->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '6', '70'))
            ->assertRedirect(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertSessionHasErrors('items.0.accepted_quantity');
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '', ''))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame('0.000', $variant->fresh()->current_stock);
    }

    public function test_admin_and_staff_can_record_damage_while_guest_and_disabled_users_cannot(): void
    {
        [$admin, $staff, $order, $item] = $this->fixture();
        $payload = $this->damagePayload($item, '1', 'Bent on delivery');

        $this->post(route('purchase-orders.receive.store', $order), $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->disabled()->create())
            ->post(route('purchase-orders.receive.store', $order), $payload)->assertRedirect('/login');
        $this->assertSame(0, RestockDamageItem::query()->count());

        foreach ([$staff, $admin] as $actor) {
            $this->actingAs($actor)->post(route('purchase-orders.receive.store', $order), $this->damagePayload(
                $item, '1', 'Bent on delivery', reference: 'DR-DAMAGE',
            ))->assertRedirect(route('purchase-orders.show', $order));
        }
        $this->assertSame(2, RestockDamageItem::query()->count());
        $this->assertSame([$staff->id, $admin->id], Restock::query()->orderBy('id')->pluck('recorded_by')->all());
    }

    public function test_damage_only_post_preserves_stock_cost_demand_and_pending_status_and_renders_history(): void
    {
        [, $staff, $order, $item, $variant] = $this->fixture();
        $beforeMovements = StockMovement::query()->count();
        $form = $this->actingAs($staff)->get(route('purchase-orders.receive.create', $order))->assertOk();
        $form->assertSee('Damaged quantity (piece)')->assertSee('Damage note')
            ->assertSee('5.000 piece')->assertSee('Damage does not reduce outstanding demand.');
        $this->assertDoesNotMatchRegularExpression('/name="items\[0\]\[damaged_quantity\]"[^>]*\smax=/', $form->getContent());

        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload(
            $item, '7', "  Cracked  on\n arrival  ", reference: 'DAMAGE-ONLY',
        ))->assertRedirect(route('purchase-orders.show', $order));

        $receipt = Restock::query()->sole();
        $damage = RestockDamageItem::query()->sole();
        $this->assertSame('0.00', $receipt->total_cost);
        $this->assertSame($staff->id, $receipt->recorded_by);
        $this->assertSame('7.000', $damage->damaged_quantity);
        $this->assertSame('Cracked on arrival', $damage->damage_note);
        $this->assertSame($item->product_name_snapshot, $damage->product_name_snapshot);
        $this->assertSame($item->size_snapshot, $damage->size_snapshot);
        $this->assertSame($item->unit_snapshot, $damage->unit_snapshot);
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame(['0.000', '50.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame('5.000', $item->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $order->fresh()->status);

        $show = $this->get(route('purchase-orders.show', $order))->assertOk();
        $show->assertSee('DAMAGE-ONLY')->assertSee('Damaged (still outstanding)')
            ->assertSee('7.000 piece')->assertSee('Cracked on arrival')
            ->assertSee($staff->name)->assertSee('5.000 piece')
            ->assertSee('data-po-damage-item="'.$damage->id.'"', false);
        $this->get(route('purchase-orders.receive.create', $order))->assertOk()->assertSee('5.000 piece');
    }

    public function test_mixed_http_receipt_posts_only_accepted_stock_and_displays_both_histories(): void
    {
        [$admin, $staff, $order, $item, $variant] = $this->fixture();
        $payload = $this->receiptPayload($item, '2', '73.45');
        $payload['items'][0]['damaged_quantity'] = '7';
        $payload['items'][0]['damage_note'] = 'Broken in shipment';

        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order));

        $this->assertSame('146.90', Restock::query()->sole()->total_cost);
        $this->assertSame('2.000', RestockItem::query()->sole()->quantity);
        $this->assertSame('7.000', RestockDamageItem::query()->sole()->damaged_quantity);
        $this->assertSame('2.000', $variant->fresh()->current_stock);
        $this->assertSame('73.45', $variant->fresh()->cost_price);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('3.000', $item->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);

        $staffHtml = $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee('Accepted')->assertSee('Damaged (still outstanding)')
            ->assertSee('2.000 piece')->assertSee('7.000 piece')->assertSee('Broken in shipment')
            ->assertSee('3.000 piece')->getContent();
        foreach (['91.27', '73.45', '146.90', 'Expected unit cost', 'Actual unit cost:'] as $protected) {
            $this->assertStringNotContainsString($protected, $staffHtml);
        }
        $this->actingAs($admin)->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee('₱91.27')->assertSee('₱73.45')->assertSee('₱146.90');
        $this->get(route('purchase-orders.receive.create', $order))->assertOk()->assertSee('3.000 piece');
    }

    public function test_damage_validation_preserves_token_accepted_and_damage_inputs_on_redisplay(): void
    {
        [, $staff, $order, $item] = $this->fixture();
        $url = route('purchase-orders.receive.create', $order);
        $token = Str::uuid()->toString();
        $payload = $this->receiptPayload($item, '1', '-1', $token);
        $payload['items'][0]['damaged_quantity'] = '7';
        $payload['items'][0]['damage_note'] = 'Bent delivery';

        $this->actingAs($staff)->from($url)->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect($url)->assertSessionHasErrors('items.0.actual_unit_cost');
        $html = $this->get($url)->assertOk()->getContent();
        foreach (['value="'.$token.'"', 'value="1"', 'value="-1"', 'value="7"', 'Bent delivery'] as $entered) {
            $this->assertStringContainsString($entered, $html);
        }
        $this->assertSame(0, Restock::query()->count());

        foreach (['', '   ', str_repeat('a', 1001), ['bad']] as $note) {
            $invalid = $this->damagePayload($item, '2', $note);
            $this->from($url)->post(route('purchase-orders.receive.store', $order), $invalid)
                ->assertRedirect($url)->assertSessionHasErrors('items.0.damage_note');
        }
        $this->get($url)->assertOk()->assertSee('value="2"', false);
        $this->assertSame(0, RestockDamageItem::query()->count());
    }

    public function test_damage_quantity_errors_note_without_quantity_and_malformed_inputs_do_not_write(): void
    {
        [, $staff, $order, $item] = $this->fixture();
        $this->actingAs($staff);
        foreach (['-1', '1.1234', ['1'], '0'] as $quantity) {
            $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, $quantity, 'Broken'))
                ->assertSessionHasErrors('items.0.damaged_quantity');
        }
        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, '', 'Orphan note'))
            ->assertSessionHasErrors('items.0.damaged_quantity');
        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, '', ''))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockDamageItem::query()->count());
    }

    public function test_fractional_damage_http_accepts_three_decimals_and_whole_mode_rejects_fraction(): void
    {
        [, $staff, $order, $item] = $this->fixture();
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, '0.125', 'Fractional'))
            ->assertSessionHasErrors('items.0.damaged_quantity');
        $this->assertSame(0, Restock::query()->count());

        $variant = $this->variant($item->variant->product, ['size' => 'Bulk', 'unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($variant, $staff);
        $fractional = $this->line($order, $variant, '1.000', '18.50');
        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($fractional, '0.125', 'Measured defect'))
            ->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame('0.125', RestockDamageItem::query()->sole()->damaged_quantity);
        $this->assertSame('1.000', $fractional->outstandingQuantity());
    }

    public function test_damage_only_http_replay_and_changed_token_semantics_use_existing_ux(): void
    {
        [, $staff, $order, $item, $variant] = $this->fixture();
        $token = Str::uuid()->toString();
        $payload = $this->damagePayload($item, '7', 'Bent', $token);
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order));
        $this->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('success', 'This receipt was already recorded.');

        foreach (['damaged_quantity' => '8', 'damage_note' => 'Different note'] as $field => $value) {
            $conflict = $payload;
            $conflict['items'][0][$field] = $value;
            $this->from(route('purchase-orders.receive.create', $order))
                ->post(route('purchase-orders.receive.store', $order), $conflict)
                ->assertRedirect(route('purchase-orders.receive.create', $order))
                ->assertSessionHasErrors('submission_token');
        }
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockDamageItem::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('0.000', $variant->fresh()->current_stock);
    }

    public function test_mixed_http_replay_after_completion_and_conflict_do_not_duplicate_evidence(): void
    {
        [, $staff, $order, $item, $variant] = $this->fixture();
        $token = Str::uuid()->toString();
        $payload = $this->receiptPayload($item, '5', '73.45', $token);
        $payload['items'][0]['damaged_quantity'] = '7';
        $payload['items'][0]['damage_note'] = 'Bent';
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);

        $this->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('success', 'This receipt was already recorded.');
        $payload['items'][0]['damage_note'] = 'Different';
        $this->post(route('purchase-orders.receive.store', $order), $payload)
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHasErrors('submission_token');
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, RestockDamageItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('5.000', $variant->fresh()->current_stock);
    }

    public function test_damage_http_rejects_foreign_lines_duplicate_lines_and_server_owned_fields(): void
    {
        [, $staff, $order, $item] = $this->fixture();
        [, , $foreignOrder, $foreignItem] = $this->fixture();
        $this->actingAs($staff);
        $url = route('purchase-orders.receive.store', $order);
        $this->post($url, $this->damagePayload($foreignItem, '1', 'Foreign'))
            ->assertSessionHasErrors('items.0.purchase_order_item_id');

        $duplicate = $this->damagePayload($item, '1', 'Duplicate');
        $duplicate['items'][] = $duplicate['items'][0];
        $this->post($url, $duplicate)->assertSessionHasErrors('items.0.purchase_order_item_id');

        foreach ([
            ['recorded_by' => 999],
            ['actor_id' => 999],
            ['purchase_order_id' => $foreignOrder->id],
            ['total_cost' => '0.00'],
            ['items' => [[...$this->damagePayload($item, '1', 'Tampered')['items'][0], 'product_name_snapshot' => 'Spoofed']]],
        ] as $tampering) {
            $payload = array_replace_recursive($this->damagePayload($item, '1', 'Tampered'), $tampering);
            $this->post($url, $payload)->assertSessionHasErrors();
        }
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockDamageItem::query()->count());
    }

    public function test_damage_history_is_eager_loaded_and_has_no_mutation_routes(): void
    {
        [, $staff, $order, $item] = $this->fixture();
        $this->actingAs($staff);
        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, '1', 'First'));
        $this->post(route('purchase-orders.receive.store', $order), $this->damagePayload($item, '2', 'Second'));
        $damageQueries = 0;
        DB::listen(function ($query) use (&$damageQueries): void {
            if (str_contains(strtolower($query->sql), 'restock_damage_items')) {
                $damageQueries++;
            }
        });

        $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee('First')->assertSee('Second');
        $this->assertSame(1, $damageQueries);
        $damageId = RestockDamageItem::query()->firstOrFail()->id;
        $this->get('/purchase-orders/'.$order->id.'/damage-items/'.$damageId)->assertNotFound();
        $this->patch('/purchase-orders/'.$order->id.'/damage-items/'.$damageId, [])->assertNotFound();
        $this->delete('/purchase-orders/'.$order->id.'/damage-items/'.$damageId)->assertNotFound();
    }

    /** @return array{User, User, PurchaseOrder, PurchaseOrderItem, ProductVariant, ?PurchaseOrderItem} */
    private function fixture(bool $twoLines = false): array
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product, ['current_stock' => '0.000']);
        $this->initialize($variant, $admin);
        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = $admin->id;
        $purchaseOrder->supplier_name = 'Receiving Supplier';
        $purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $purchaseOrder->save();
        $item = $this->line($purchaseOrder, $variant, '5.000', '91.27');
        $other = null;
        if ($twoLines) {
            $secondVariant = $this->variant($product, ['size' => 'Second']);
            $this->initialize($secondVariant, $admin);
            $other = $this->line($purchaseOrder, $secondVariant, '2.000', '18.50');
        }

        return [$admin, $staff, $purchaseOrder, $item, $variant, $other];
    }

    private function line(PurchaseOrder $purchaseOrder, ProductVariant $variant, string $quantity, string $cost): PurchaseOrderItem
    {
        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $purchaseOrder->id;
        $item->product_variant_id = $variant->id;
        $item->product_name_snapshot = 'Receiving Product';
        $item->size_snapshot = $variant->size;
        $item->type_series_snapshot = $variant->type_series;
        $item->thickness_snapshot = $variant->thickness;
        $item->unit_snapshot = $variant->unit;
        $item->ordered_quantity = $quantity;
        $item->expected_unit_cost = $cost;
        $item->save();

        return $item;
    }

    /** @return array<string, mixed> */
    private function receiptPayload(PurchaseOrderItem $item, string $quantity, string $cost, ?string $token = null, ?string $reference = null): array
    {
        return [
            'submission_token' => $token ?? Str::uuid()->toString(),
            'reference_text' => $reference,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'accepted_quantity' => $quantity,
                'actual_unit_cost' => $cost,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function damagePayload(PurchaseOrderItem $item, mixed $quantity, mixed $note, ?string $token = null, ?string $reference = null): array
    {
        return [
            'submission_token' => $token ?? Str::uuid()->toString(),
            'reference_text' => $reference,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'accepted_quantity' => '',
                'actual_unit_cost' => '',
                'damaged_quantity' => $quantity,
                'damage_note' => $note,
            ]],
        ];
    }
}
