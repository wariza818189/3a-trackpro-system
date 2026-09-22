<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Support\Str;

final class PurchaseOrderEditingTest extends PurchaseOrderCreationTestCase
{
    public function test_edit_and_update_http_boundaries_require_an_active_admin_and_allow_another_admin(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Authorization']);
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $payload = $this->updatePayload($purchaseOrder, [$this->line($variant, '3', '30')]);

        $this->get(route('purchase-orders.edit', $purchaseOrder))->assertRedirect('/login');
        $this->patch(route('purchase-orders.update', $purchaseOrder), $payload)->assertRedirect('/login');

        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->patch(route('purchase-orders.update', $purchaseOrder), $payload)->assertForbidden();

        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabledAdmin)->get(route('purchase-orders.edit', $purchaseOrder))->assertRedirect('/login');
        $this->actingAs($disabledAdmin)->patch(route('purchase-orders.update', $purchaseOrder), $payload)->assertRedirect('/login');

        $editor = User::factory()->admin()->create();
        $this->actingAs($editor)->get(route('purchase-orders.edit', $purchaseOrder))->assertOk();
        $this->actingAs($editor)
            ->patch(route('purchase-orders.update', $purchaseOrder), $payload)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHas('success', 'Purchase Order updated successfully.');

        $this->assertSame($this->admin->id, $purchaseOrder->fresh()->created_by);
        $this->actingAs($this->admin)->get('/purchase-orders/999999/edit')->assertNotFound();
    }

    public function test_only_pending_orders_are_editable_through_get_or_patch(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Status Boundary']);

        foreach ([
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED,
            PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER,
        ] as $status) {
            $purchaseOrder = $this->createOrder([$this->line($variant)], "{$status} Supplier");
            $purchaseOrder->status = $status;
            $purchaseOrder->save();
            $revision = $this->updater()->revision($purchaseOrder);

            $this->actingAs($this->admin)
                ->get(route('purchase-orders.edit', $purchaseOrder))
                ->assertStatus(409);
            $this->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => 'Must Not Change',
                'notes' => null,
                'items' => [$this->line($variant, '9', '90')],
            ])->assertRedirect(route('purchase-orders.show', $purchaseOrder))
                ->assertSessionHasErrors('purchase_order');

            $fresh = $purchaseOrder->fresh();
            $this->assertSame("{$status} Supplier", $fresh->supplier_name);
            $this->assertSame($status, $fresh->status);
            $this->assertSame('2.000', $fresh->items()->sole()->ordered_quantity);
        }
    }

    public function test_fresh_edit_form_uses_saved_header_line_snapshots_and_current_revision_without_server_owned_inputs(): void
    {
        $product = $this->product($this->category(['name' => 'Saved Category']), ['name' => 'Saved Product']);
        $variant = $this->variant($product, [
            'size' => 'Saved Size',
            'type_series' => 'Saved Series',
            'thickness' => 'Saved Thickness',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
        ]);
        $this->initialize($variant);
        $purchaseOrder = $this->createOrder([$this->line($variant, '1.25', '25.5')], 'Saved Supplier', 'Saved notes');
        $revision = $this->updater()->revision($purchaseOrder);

        $product->name = 'Current Renamed Product';
        $product->save();
        $response = $this->actingAs($this->admin)->get(route('purchase-orders.edit', $purchaseOrder));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Edit Purchase Order #'.$purchaseOrder->id)
            ->assertSee('value="Saved Supplier"', false)
            ->assertSee('Saved notes')
            ->assertSee('data-po-draft-product>Saved Product<', false)
            ->assertSee('data-product="Saved Product"', false)
            ->assertSee('data-retained="1"', false)
            ->assertSee('Saved Size · Saved Series · Saved Thickness · kg')
            ->assertSee('value="1.250"', false)
            ->assertSee('value="25.50"', false)
            ->assertSee('name="expected_revision" value="'.$revision.'"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertDontSee('name="submission_token"', false)
            ->assertDontSee('name="created_by"', false)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="parent_purchase_order_id"', false)
            ->assertDontSee('name="purchase_order_item_id"', false);
        $this->assertMatchesRegularExpression('/name="expected_revision" value="[0-9a-f]{64}"/', $content);
    }

    public function test_archived_retained_line_keeps_historical_edit_controls_but_is_not_a_picker_option(): void
    {
        $category = $this->category(['name' => 'Archived Saved Category']);
        $product = $this->product($category, ['name' => 'Archived Saved Product']);
        $variant = $this->variant($product, ['size' => 'Archived Saved Size']);
        $this->initialize($variant);
        $purchaseOrder = $this->createOrder([$this->line($variant, '4', '12')]);

        $category->status = Category::STATUS_ARCHIVED;
        $category->save();

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.edit', $purchaseOrder));
        $content = $response->getContent();
        $response->assertOk()
            ->assertSee('Archived Saved Product')
            ->assertSee('Archived Saved Size')
            ->assertSee('data-po-historical-line', false)
            ->assertSee('data-po-retained-unavailable', false)
            ->assertSee('this saved line may still be edited or removed')
            ->assertSee('data-po-remove', false)
            ->assertSee('name="items[0][ordered_quantity]" value="4.000"', false)
            ->assertSee('name="items[0][expected_unit_cost]" value="12.00"', false);
        $this->assertSame(1, substr_count($content, 'data-id="'.$variant->id.'"'));
    }

    public function test_edit_picker_contains_all_current_eligible_groups_and_excludes_ineligible_catalog_rows(): void
    {
        $base = $this->eligibleVariant(['size' => 'Retained Eligible', 'current_stock' => '10.000']);
        $purchaseOrder = $this->createOrder([$this->line($base)]);
        $uncovered = $this->eligibleVariant(['size' => 'Picker Uncovered', 'current_stock' => '0.000']);
        $covered = $this->eligibleVariant(['size' => 'Picker Covered', 'current_stock' => '1.000']);
        $healthy = $this->eligibleVariant(['size' => 'Picker Healthy', 'current_stock' => '20.000']);
        $this->coverVariant($covered);

        $uninitialized = $this->variant($this->product($this->category()), ['size' => 'Picker Uninitialized']);
        $inactive = $this->eligibleVariant(['size' => 'Picker Inactive']);
        $inactive->status = ProductVariant::STATUS_ARCHIVED;
        $inactive->save();
        $inactiveProduct = $this->eligibleVariant(['size' => 'Picker Inactive Product']);
        $inactiveProduct->product->status = Product::STATUS_ARCHIVED;
        $inactiveProduct->product->save();
        $inactiveCategory = $this->eligibleVariant(['size' => 'Picker Inactive Category']);
        $inactiveCategory->product->category->status = Category::STATUS_ARCHIVED;
        $inactiveCategory->product->category->save();

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.edit', $purchaseOrder));
        $response->assertOk()
            ->assertSee('Retained Eligible')
            ->assertSee('Picker Uncovered')
            ->assertSee('Picker Covered')
            ->assertSee('Picker Healthy')
            ->assertDontSee('Picker Uninitialized')
            ->assertDontSee('Picker Inactive')
            ->assertDontSee('Picker Inactive Product')
            ->assertDontSee('Picker Inactive Category')
            ->assertDontSee('Suggested order quantity')
            ->assertDontSee('data-suggested-cost', false);

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($javascript);
        $this->assertStringContainsString('[data-purchase-order-form]', $javascript);
        $this->assertStringContainsString('button.disabled = alreadySelected', $javascript);
    }

    public function test_valid_patch_updates_header_and_final_line_set_without_changing_server_owned_header_fields(): void
    {
        $removed = $this->eligibleVariant(['size' => 'HTTP Removed']);
        $retained = $this->eligibleVariant(['size' => 'HTTP Retained']);
        $added = $this->eligibleVariant([
            'size' => 'HTTP Added',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
        ]);
        $parent = $this->createOrder([$this->line($this->eligibleVariant(['size' => 'HTTP Parent']))]);
        $purchaseOrder = $this->createOrder([$this->line($removed), $this->line($retained)]);
        $purchaseOrder->parent_purchase_order_id = $parent->id;
        $purchaseOrder->save();
        $immutable = $purchaseOrder->only(['submission_token', 'created_by', 'status', 'parent_purchase_order_id']);

        $response = $this->actingAs($this->admin)->patch(
            route('purchase-orders.update', $purchaseOrder),
            $this->updatePayload($purchaseOrder, [
                $this->line($retained, '7', '70'),
                $this->line($added, '1.25', '17.5'),
            ], [
                'supplier_name' => "  Updated\u{2003} Supplier  ",
                'notes' => "  updated\n notes  ",
            ]),
        );

        $response->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Purchase Order updated successfully.')
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $fresh = $purchaseOrder->fresh();
        $items = $fresh->items()->orderBy('product_variant_id')->get();
        $this->assertSame('Updated Supplier', $fresh->supplier_name);
        $this->assertSame('updated notes', $fresh->notes);
        $this->assertSame($immutable, $fresh->only(array_keys($immutable)));
        $this->assertSame([$retained->id, $added->id], $items->pluck('product_variant_id')->all());
        $this->assertSame('7.000', $items->firstWhere('product_variant_id', $retained->id)->ordered_quantity);
        $this->assertSame('70.00', $items->firstWhere('product_variant_id', $retained->id)->expected_unit_cost);
        $this->assertSame('1.250', $items->firstWhere('product_variant_id', $added->id)->ordered_quantity);
        $this->assertSame('17.50', $items->firstWhere('product_variant_id', $added->id)->expected_unit_cost);
        $this->assertDatabaseMissing('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_variant_id' => $removed->id,
        ]);
    }

    public function test_whole_quantity_rejects_fractional_input_while_fractional_mode_succeeds(): void
    {
        $whole = $this->eligibleVariant(['size' => 'HTTP Whole']);
        $purchaseOrder = $this->createOrder([$this->line($whole)]);
        $revision = $this->updater()->revision($purchaseOrder);

        $this->actingAs($this->admin)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => 'Whole Supplier',
                'notes' => null,
                'items' => [$this->line($whole, '1.250', '10')],
            ])
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('items.0.ordered_quantity')
            ->assertSessionHasInput('expected_revision', $revision);
        $this->assertSame('2.000', $purchaseOrder->fresh()->items()->sole()->ordered_quantity);

        $fractional = $this->eligibleVariant(['size' => 'HTTP Fractional', 'quantity_mode' => 'fractional']);
        $fractionalOrder = $this->createOrder([$this->line($fractional)]);
        $this->actingAs($this->admin)
            ->patch(
                route('purchase-orders.update', $fractionalOrder),
                $this->updatePayload($fractionalOrder, [$this->line($fractional, '1.250', '10')]),
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase-orders.show', $fractionalOrder));
        $this->assertSame('1.250', $fractionalOrder->fresh()->items()->sole()->ordered_quantity);
    }

    public function test_structural_validation_rejects_malformed_and_server_owned_fields_without_mutation(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Validation']);
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $beforeHeader = $purchaseOrder->only(['supplier_name', 'notes', 'status', 'created_by', 'parent_purchase_order_id']);
        $beforeItem = $purchaseOrder->items()->sole()->only(['ordered_quantity', 'expected_unit_cost']);
        $base = $this->updatePayload($purchaseOrder, [$this->line($variant)]);

        $missingRevision = $base;
        unset($missingRevision['expected_revision']);
        $cases = [
            [$missingRevision, 'expected_revision'],
            [array_replace($base, ['expected_revision' => 'not-a-revision']), 'expected_revision'],
            [array_replace($base, ['supplier_name' => '   ']), 'supplier_name'],
            [array_replace($base, ['items' => []]), 'items'],
            [array_replace($base, ['items' => [$this->line($variant), $this->line($variant)]]), 'items.1.product_variant_id'],
            [array_replace($base, ['items' => [$this->line($variant, '1e2', '10')]]), 'items.0.ordered_quantity'],
            [array_replace($base, ['items' => [$this->line($variant, '1', '1.001')]]), 'items.0.expected_unit_cost'],
            [array_replace($base, [
                'submission_token' => Str::uuid()->toString(),
                'created_by' => 999,
                'status' => PurchaseOrder::STATUS_COMPLETED,
                'parent_purchase_order_id' => 999,
                'updated_at' => '2030-01-01 00:00:00',
                'stock' => '999',
            ]), 'request'],
            [array_replace($base, ['items' => [[
                ...$this->line($variant),
                'purchase_order_item_id' => 999,
                'product_name_snapshot' => 'Spoofed',
                'quantity_mode' => 'fractional',
            ]]]), 'items.0'],
        ];

        $this->actingAs($this->admin);
        foreach ($cases as [$payload, $error]) {
            $this->from(route('purchase-orders.edit', $purchaseOrder))
                ->patch(route('purchase-orders.update', $purchaseOrder), $payload)
                ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
                ->assertSessionHasErrors($error);
        }

        $fresh = $purchaseOrder->fresh();
        $this->assertSame($beforeHeader, $fresh->only(array_keys($beforeHeader)));
        $this->assertSame($beforeItem, $fresh->items()->sole()->only(array_keys($beforeItem)));
    }

    public function test_ordinary_validation_preserves_revision_and_proposed_final_line_set_without_reinserting_omissions(): void
    {
        $retained = $this->eligibleVariant(['size' => 'Draft Retained']);
        $omitted = $this->eligibleVariant(['size' => 'Draft Omitted']);
        $purchaseOrder = $this->createOrder([$this->line($retained), $this->line($omitted)]);
        $revision = $this->updater()->revision($purchaseOrder);

        $this->actingAs($this->admin)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => '',
                'notes' => 'Preserved proposed notes',
                'items' => [$this->line($retained, '8', '80')],
            ])
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('supplier_name')
            ->assertSessionHasInput('expected_revision', $revision)
            ->assertSessionHasInput('items.0.ordered_quantity', '8');

        $response = $this->get(route('purchase-orders.edit', $purchaseOrder));
        $content = $response->getContent();
        $response->assertOk()
            ->assertSee('Preserved proposed notes')
            ->assertSee('name="expected_revision" value="'.$revision.'"', false)
            ->assertSee('name="items[0][ordered_quantity]" value="8"', false)
            ->assertSee('name="items[0][expected_unit_cost]" value="80"', false);
        $this->assertSame(1, substr_count($content, 'data-po-draft-row data-id="'.$retained->id.'"'));
        $this->assertSame(0, substr_count($content, 'data-po-draft-row data-id="'.$omitted->id.'"'));
    }

    public function test_ordinary_domain_failure_preserves_archived_historical_row_and_revision(): void
    {
        $archived = $this->eligibleVariant(['size' => 'Historical Draft']);
        $purchaseOrder = $this->createOrder([$this->line($archived)]);
        $archived->status = ProductVariant::STATUS_ARCHIVED;
        $archived->save();
        $revision = $this->updater()->revision($purchaseOrder);

        $this->actingAs($this->admin)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => 'Historical Draft Supplier',
                'notes' => null,
                'items' => [$this->line($archived, '1.250', '15')],
            ])
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('items.0.ordered_quantity')
            ->assertSessionHasInput('expected_revision', $revision);

        $this->get(route('purchase-orders.edit', $purchaseOrder))
            ->assertOk()
            ->assertSee('Historical Draft')
            ->assertSee('data-po-retained-unavailable', false)
            ->assertSee('name="expected_revision" value="'.$revision.'"', false)
            ->assertSee('name="items[0][ordered_quantity]" value="1.250"', false);
    }

    public function test_new_line_that_becomes_ineligible_is_dropped_on_redisplay_with_notice(): void
    {
        $retained = $this->eligibleVariant(['size' => 'Redisplay Retained']);
        $new = $this->eligibleVariant(['size' => 'Redisplay Became Archived']);
        $purchaseOrder = $this->createOrder([$this->line($retained)]);
        $revision = $this->updater()->revision($purchaseOrder);
        $new->status = ProductVariant::STATUS_ARCHIVED;
        $new->save();

        $this->actingAs($this->admin)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => 'Redisplay Supplier',
                'notes' => 'Preserved redisplay notes',
                'items' => [$this->line($retained, '3', '30'), $this->line($new, '4', '40')],
            ])
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('items.1.product_variant_id')
            ->assertSessionHasInput('expected_revision', $revision);

        $response = $this->get(route('purchase-orders.edit', $purchaseOrder));
        $content = $response->getContent();
        $response->assertOk()
            ->assertSee('previously selected variant became unavailable')
            ->assertSee('Preserved redisplay notes')
            ->assertSee('name="expected_revision" value="'.$revision.'"', false);
        $this->assertSame(1, substr_count($content, 'data-po-draft-row data-id="'.$retained->id.'"'));
        $this->assertSame(0, substr_count($content, 'data-po-draft-row data-id="'.$new->id.'"'));
    }

    public function test_stale_revision_discards_old_input_and_fresh_edit_uses_current_persisted_state(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Stale Variant']);
        $purchaseOrder = $this->createOrder([$this->line($variant, '2', '20')], 'Original Supplier', 'Original notes');
        $staleRevision = $this->updater()->revision($purchaseOrder);
        $purchaseOrder->supplier_name = 'Current Supplier';
        $purchaseOrder->notes = 'Current notes';
        $purchaseOrder->save();
        $item = $purchaseOrder->items()->sole();
        $item->ordered_quantity = '6.000';
        $item->expected_unit_cost = '60.00';
        $item->save();
        $currentRevision = $this->updater()->revision($purchaseOrder);

        $this->actingAs($this->admin)
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $staleRevision,
                'supplier_name' => 'Stale Supplier',
                'notes' => 'Stale notes',
                'items' => [$this->line($variant, '9', '90')],
            ])
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors([
                'expected_revision' => 'This Purchase Order changed while you were editing it. Review the latest values and try again.',
            ])
            ->assertSessionMissing('_old_input');

        $response = $this->get(route('purchase-orders.edit', $purchaseOrder));
        $response->assertOk()
            ->assertSee('value="Current Supplier"', false)
            ->assertSee('Current notes')
            ->assertSee('value="6.000"', false)
            ->assertSee('value="60.00"', false)
            ->assertSee('name="expected_revision" value="'.$currentRevision.'"', false)
            ->assertDontSee('Stale Supplier')
            ->assertDontSee('Stale notes')
            ->assertDontSee('value="9"', false)
            ->assertDontSee('value="90"', false);
        $this->assertSame('Current Supplier', $purchaseOrder->fresh()->supplier_name);
    }

    public function test_race_to_nonpending_redirects_to_read_only_show_without_partial_update(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Race Status']);
        $purchaseOrder = $this->createOrder([$this->line($variant)], 'Race Original', 'Race original notes');
        $revision = $this->updater()->revision($purchaseOrder);
        $purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $purchaseOrder->save();

        $this->actingAs($this->admin)
            ->patch(route('purchase-orders.update', $purchaseOrder), [
                'expected_revision' => $revision,
                'supplier_name' => 'Race Stale Draft',
                'notes' => 'Must not apply',
                'items' => [$this->line($variant, '9', '90')],
            ])
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors([
                'purchase_order' => 'This Purchase Order is now read-only and was not updated.',
            ]);

        $fresh = $purchaseOrder->fresh();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $fresh->status);
        $this->assertSame('Race Original', $fresh->supplier_name);
        $this->assertSame('Race original notes', $fresh->notes);
        $this->assertSame('2.000', $fresh->items()->sole()->ordered_quantity);
    }

    public function test_successful_patch_produces_a_new_revision_on_the_next_edit_get(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Revision HTTP']);
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $before = $this->updater()->revision($purchaseOrder);

        $this->actingAs($this->admin)
            ->patch(
                route('purchase-orders.update', $purchaseOrder),
                $this->updatePayload($purchaseOrder, [$this->line($variant, '5', '50')], ['supplier_name' => 'Revision Updated']),
            )
            ->assertSessionHasNoErrors();

        $after = $this->updater()->revision($purchaseOrder);
        $this->assertNotSame($before, $after);
        $this->get(route('purchase-orders.edit', $purchaseOrder))
            ->assertOk()
            ->assertSee('name="expected_revision" value="'.$after.'"', false)
            ->assertDontSee('name="expected_revision" value="'.$before.'"', false);
    }

    /** @param array<string, mixed> $attributes */
    private function eligibleVariant(array $attributes = []): ProductVariant
    {
        $variant = $this->variant($this->product($this->category()), $attributes);
        $this->initialize($variant, quantity: (string) ($attributes['current_stock'] ?? '0.000'));

        return $variant;
    }

    /** @return array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string} */
    private function line(ProductVariant $variant, string $quantity = '2', string $cost = '25.50'): array
    {
        return [
            'product_variant_id' => (int) $variant->getKey(),
            'ordered_quantity' => $quantity,
            'expected_unit_cost' => $cost,
        ];
    }

    /** @param list<array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}> $items */
    private function createOrder(
        array $items,
        string $supplier = 'Original Supplier',
        ?string $notes = 'Original notes',
    ): PurchaseOrder {
        return app(CreatePurchaseOrder::class)->execute(
            $this->admin,
            Str::uuid()->toString(),
            $supplier,
            $notes,
            $items,
        );
    }

    /**
     * @param  list<array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(PurchaseOrder $purchaseOrder, array $items, array $overrides = []): array
    {
        return array_replace([
            'expected_revision' => $this->updater()->revision($purchaseOrder),
            'supplier_name' => 'Updated Supplier',
            'notes' => 'Updated notes',
            'items' => $items,
        ], $overrides);
    }

    private function updater(): UpdatePurchaseOrder
    {
        return app(UpdatePurchaseOrder::class);
    }
}
