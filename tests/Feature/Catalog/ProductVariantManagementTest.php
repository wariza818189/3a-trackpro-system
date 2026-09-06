<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductVariantManagementTest extends CatalogTestCase
{
    public function test_create_normalizes_identity_uses_defaults_and_writes_no_movement(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());

        $payload = $this->validVariant([
            'size' => null,
            'type_series' => "  Grade\n  10.9 ",
            'thickness' => null,
            'unit' => ' KG ',
        ]);
        $this->actingAs($admin)->post(route('product-variants.store', $product), $payload)->assertSessionHasNoErrors();

        $variant = ProductVariant::query()->sole();
        $this->assertSame('', $variant->size);
        $this->assertSame('Grade 10.9', $variant->type_series);
        $this->assertSame('', $variant->thickness);
        $this->assertSame('kg', $variant->unit);
        $this->assertSame('0.000', $variant->current_stock);
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $variant->status);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_create_requires_active_product_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category(['status' => Category::STATUS_ARCHIVED]);
        $product = $this->product($category);

        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant())->assertSessionHasErrors('product_id');
        $this->assertSame(0, ProductVariant::query()->count());
    }

    public function test_supported_units_quantity_modes_and_composite_uniqueness_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $this->variant($product);

        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant(['unit' => 'box']))->assertSessionHasErrors('unit');
        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant(['quantity_mode' => 'measured']))->assertSessionHasErrors('quantity_mode');
        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant([
            'size' => ' m8 ', 'type_series' => ' grade   8.8 ', 'unit' => 'PIECE',
        ]))->assertSessionHasErrors('size');
        $this->assertSame(1, ProductVariant::query()->count());
    }

    public function test_price_threshold_scale_bounds_and_whole_threshold_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $invalid = [
            ['cost_price', '-0.01'], ['cost_price', '1.999'], ['cost_price', '10000000000.00'],
            ['selling_price', '0.00'], ['selling_price', '1.999'], ['selling_price', '10000000000.00'],
            ['low_stock_threshold', '-0.001'], ['low_stock_threshold', '1.0009'], ['low_stock_threshold', '100000000000.000'],
        ];

        foreach ($invalid as $index => [$field, $value]) {
            $payload = $this->validVariant(['size' => 'case-'.$index, $field => $value]);
            $this->actingAs($admin)->post(route('product-variants.store', $product), $payload)->assertSessionHasErrors($field);
        }

        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant([
            'size' => 'fractional-threshold', 'low_stock_threshold' => '1.250',
        ]))->assertSessionHasErrors('low_stock_threshold');
        $this->actingAs($admin)->post(route('product-variants.store', $product), $this->validVariant([
            'size' => 'fractional-mode', 'quantity_mode' => 'fractional', 'low_stock_threshold' => '1.250',
        ]))->assertSessionHasNoErrors();
    }

    public function test_product_stock_and_status_identifiers_are_prohibited(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());

        foreach (['product_id' => 999, 'current_stock' => '12.000', 'status' => 'archived'] as $field => $value) {
            $payload = $this->validVariant(['size' => $field, $field => $value]);
            $this->actingAs($admin)->post(route('product-variants.store', $product), $payload)->assertSessionHasErrors($field);
        }
        $this->assertSame(0, ProductVariant::query()->count());
    }

    public function test_price_threshold_identity_and_cost_can_update_before_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        $payload = $this->validVariant([
            'size' => ' M10 ', 'type_series' => ' Grade   10.9 ', 'cost_price' => '55.25',
            'selling_price' => '90.00', 'low_stock_threshold' => '3.000',
        ]);
        $this->actingAs($admin)->patch(route('product-variants.update', $variant), $payload)->assertSessionHasNoErrors();
        $variant->refresh();
        $this->assertSame('M10', $variant->size);
        $this->assertSame('Grade 10.9', $variant->type_series);
        $this->assertSame('55.25', $variant->cost_price);
        $this->assertSame('90.00', $variant->selling_price);
        $this->assertSame('3.000', $variant->low_stock_threshold);
    }

    public function test_identity_edit_is_denied_for_each_activity_type_and_nonzero_stock(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['sale_items', 'restock_items', 'stock_movements', 'stock'] as $marker) {
            $product = $this->product($this->category(['name' => 'Category '.$marker]), ['name' => 'Product '.$marker]);
            $variant = $this->variant($product, ['size' => $marker]);
            if ($marker === 'stock') {
                DB::table('product_variants')->where('id', $variant->id)->update(['current_stock' => '0.001']);
            } else {
                DB::table($marker)->insert(['product_variant_id' => $variant->id]);
            }

            $payload = $this->validVariant(['size' => 'changed-'.$marker]);
            $this->actingAs($admin)->patch(route('product-variants.update', $variant), $payload)->assertSessionHasErrors('size');
            $this->assertSame($marker, $variant->fresh()->size);
        }
    }

    public function test_cost_is_immutable_after_first_restock_but_price_and_threshold_can_change(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        DB::table('restock_items')->insert(['product_variant_id' => $variant->id]);

        $this->actingAs($admin)->patch(route('product-variants.update', $variant), $this->validVariant(['cost_price' => '60.00']))->assertSessionHasErrors('cost_price');
        $this->actingAs($admin)->patch(route('product-variants.update', $variant), $this->validVariant([
            'selling_price' => '80.00', 'low_stock_threshold' => '4.000',
        ]))->assertSessionHasNoErrors();
        $variant->refresh();
        $this->assertSame('50.00', $variant->cost_price);
        $this->assertSame('80.00', $variant->selling_price);
        $this->assertSame('4.000', $variant->low_stock_threshold);
    }

    public function test_archive_with_history_and_zero_stock_preserves_history_stock_and_movements(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        DB::table('sale_items')->insert(['product_variant_id' => $variant->id]);
        $beforeMovements = DB::table('stock_movements')->count();

        $this->actingAs($admin)->patch(route('product-variants.archive', $variant))->assertSessionHasNoErrors();
        $variant->refresh();
        $this->assertSame(ProductVariant::STATUS_ARCHIVED, $variant->status);
        $this->assertSame('0.000', $variant->current_stock);
        $this->assertSame(1, DB::table('sale_items')->count());
        $this->assertSame($beforeMovements, DB::table('stock_movements')->count());
    }

    public function test_archive_with_stock_is_denied_without_changing_stock_or_movements(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '3.000']);

        $this->actingAs($admin)->patch(route('product-variants.archive', $variant))->assertSessionHasErrors('status');
        $variant->refresh();
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $variant->status);
        $this->assertSame('3.000', $variant->current_stock);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_reactivation_requires_active_product_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();
        $product = $this->product($category, ['status' => Product::STATUS_ARCHIVED]);
        $variant = $this->variant($product, ['status' => ProductVariant::STATUS_ARCHIVED]);

        $this->actingAs($admin)->patch(route('product-variants.reactivate', $variant))->assertSessionHasErrors('status');
        $product->status = Product::STATUS_ACTIVE;
        $product->save();
        $category->status = Category::STATUS_ARCHIVED;
        $category->save();
        $this->actingAs($admin)->patch(route('product-variants.reactivate', $variant))->assertSessionHasErrors('status');
        $category->status = Category::STATUS_ACTIVE;
        $category->save();
        $this->actingAs($admin)->patch(route('product-variants.reactivate', $variant))->assertSessionHasNoErrors();
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $variant->fresh()->status);
    }

    public function test_variant_update_rejects_protected_fields_and_has_no_delete_route(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        foreach (['product_id' => 999, 'current_stock' => '9.000', 'status' => 'archived'] as $field => $value) {
            $this->actingAs($admin)->patch(route('product-variants.update', $variant), $this->validVariant([$field => $value]))->assertSessionHasErrors($field);
        }
        $variant->refresh();
        $this->assertSame('0.000', $variant->current_stock);
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $variant->status);
        $this->actingAs($admin)->delete('/product-variants/'.$variant->id)->assertMethodNotAllowed();
    }
}
