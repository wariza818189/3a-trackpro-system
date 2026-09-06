<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductManagementTest extends CatalogTestCase
{
    public function test_create_uses_active_route_parent_normalizes_name_and_cannot_be_overridden(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();
        $other = $this->category(['name' => 'Other']);

        $this->actingAs($admin)->post(route('products.store', $category), ['name' => '  Machine   Screw  '])
            ->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['category_id' => $category->id, 'name' => 'Machine Screw', 'status' => Product::STATUS_ACTIVE]);

        $this->actingAs($admin)->post(route('products.store', $category), ['name' => 'Nut', 'category_id' => $other->id])
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('products', ['name' => 'Nut']);
    }

    public function test_duplicate_is_rejected_within_category_but_allowed_elsewhere(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->category();
        $second = $this->category(['name' => 'Structural']);
        $this->product($first, ['name' => 'Machine Bolt']);

        $this->actingAs($admin)->post(route('products.store', $first), ['name' => ' machine   bolt '])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('products.store', $second), ['name' => 'MACHINE BOLT'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['category_id' => $second->id, 'name' => 'MACHINE BOLT']);
    }

    public function test_create_under_archived_category_and_edit_archived_product_are_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category(['status' => Category::STATUS_ARCHIVED]);
        $product = $this->product($category, ['status' => Product::STATUS_ARCHIVED]);

        $this->actingAs($admin)->post(route('products.store', $category), ['name' => 'Nut'])->assertSessionHasErrors('category_id');
        $this->actingAs($admin)->get(route('products.edit', $product))->assertStatus(409);
        $this->actingAs($admin)->patch(route('products.update', $product), ['name' => 'Nut', 'category_id' => $category->id])->assertSessionHasErrors('category_id');
    }

    public function test_product_name_update_and_safe_category_move_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $source = $this->category();
        $destination = $this->category(['name' => 'Structural']);
        $product = $this->product($source);
        $this->variant($product);

        $this->actingAs($admin)->patch(route('products.update', $product), ['name' => '  Hex   Bolt ', 'category_id' => $destination->id])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('Hex Bolt', $product->name);
        $this->assertSame($destination->id, $product->category_id);
    }

    public function test_category_move_is_denied_for_each_history_type_and_nonzero_stock(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['sale_items', 'restock_items', 'stock_movements', 'stock'] as $marker) {
            $source = $this->category(['name' => 'Source '.$marker]);
            $destination = $this->category(['name' => 'Destination '.$marker]);
            $product = $this->product($source, ['name' => 'Product '.$marker]);
            $variant = $this->variant($product, ['size' => $marker]);

            if ($marker === 'stock') {
                DB::table('product_variants')->where('id', $variant->id)->update(['current_stock' => '1.000']);
            } else {
                DB::table($marker)->insert(['product_variant_id' => $variant->id]);
            }

            $this->actingAs($admin)->patch(route('products.update', $product), [
                'name' => $product->name,
                'category_id' => $destination->id,
            ])->assertSessionHasErrors('category_id');
            $this->assertSame($source->id, $product->fresh()->category_id);
        }
    }

    public function test_archive_requires_no_active_variants_and_reactivation_requires_active_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product);

        $this->actingAs($admin)->patch(route('products.archive', $product))->assertSessionHasErrors('status');
        $variant->status = 'archived';
        $variant->save();
        $this->actingAs($admin)->patch(route('products.archive', $product))->assertSessionHasNoErrors();
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);

        $category->status = Category::STATUS_ARCHIVED;
        $category->save();
        $this->actingAs($admin)->patch(route('products.reactivate', $product))->assertSessionHasErrors('status');
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);
    }

    public function test_product_has_no_delete_route(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $this->actingAs($admin)->delete('/products/'.$product->id)->assertMethodNotAllowed();
    }
}
