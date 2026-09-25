<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class ProductDetailTest extends CatalogTestCase
{
    public function test_admin_can_inspect_active_and_archived_products_and_variants(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category(['status' => Category::STATUS_ARCHIVED]);
        $active = $this->product($category, ['name' => 'Active Product']);
        $archived = $this->product($category, ['name' => 'Archived Product', 'status' => Product::STATUS_ARCHIVED]);
        $this->variant($archived, ['size' => 'Active Size']);
        $this->variant($archived, ['size' => 'Archived Size', 'status' => ProductVariant::STATUS_ARCHIVED]);

        $this->actingAs($admin)->get(route('products.show', $active))
            ->assertOk()->assertSee('Active Product')->assertSee('Fasteners')->assertSee('active');
        $this->actingAs($admin)->get(route('products.show', $archived))
            ->assertOk()->assertSee('Archived Product')->assertSee('Fasteners')
            ->assertSee('archived')->assertSee('Active Size')->assertSee('Archived Size');
    }

    public function test_staff_can_inspect_only_active_product_hierarchy_and_active_variants(): void
    {
        $staff = User::factory()->create();
        $category = $this->category();
        $product = $this->product($category);
        $this->variant($product, ['size' => 'Visible Size']);
        $this->variant($product, ['size' => 'Hidden Size', 'status' => ProductVariant::STATUS_ARCHIVED]);
        $archived = $this->product($category, ['name' => 'Archived Product', 'status' => Product::STATUS_ARCHIVED]);
        $inactiveCategory = $this->category(['name' => 'Inactive Category', 'status' => Category::STATUS_ARCHIVED]);
        $inactiveHierarchy = $this->product($inactiveCategory, ['name' => 'Inactive Hierarchy Product']);

        $this->actingAs($staff)->get(route('products.show', $product))
            ->assertOk()->assertSee('Machine Bolt')->assertSee('Fasteners')
            ->assertSee('Visible Size')->assertDontSee('Hidden Size');
        $this->actingAs($staff)->get(route('products.show', $archived))->assertNotFound();
        $this->actingAs($staff)->get(route('products.show', $inactiveHierarchy))->assertNotFound();
    }

    public function test_guest_and_disabled_account_cannot_inspect_product_detail(): void
    {
        $product = $this->product($this->category());

        $this->get(route('products.show', $product))->assertRedirect('/login');
        $this->actingAs(User::factory()->disabled()->create())
            ->get(route('products.show', $product))->assertRedirect('/login');
    }

    public function test_detail_displays_each_variant_field_exact_quantity_and_derived_stock_state_without_cost(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $this->variant($product, [
            'size' => 'Whole Zero', 'type_series' => 'Series W', 'thickness' => '3 mm',
            'unit' => 'piece', 'quantity_mode' => 'whole', 'selling_price' => '78.42',
            'current_stock' => '0.000', 'low_stock_threshold' => '2.000', 'cost_price' => '9123.45',
        ]);
        $this->variant($product, [
            'size' => 'Fractional Low', 'type_series' => 'Series F', 'thickness' => '4 mm',
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'selling_price' => '91.07',
            'current_stock' => '0.125', 'low_stock_threshold' => '0.125', 'cost_price' => '8123.45',
        ]);
        $this->variant($product, [
            'size' => 'Whole Good', 'type_series' => 'Series G', 'thickness' => '5 mm',
            'unit' => 'sheet', 'quantity_mode' => 'whole', 'selling_price' => '101.30',
            'current_stock' => '10.000', 'low_stock_threshold' => '9.000', 'cost_price' => '7123.45',
            'status' => ProductVariant::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($admin)->get(route('products.show', $product))->assertOk()
            ->assertSee('Machine Bolt')->assertSee('Fasteners')->assertSee('Status');
        foreach (['Size', 'Type / series', 'Thickness', 'Unit', 'Quantity mode', 'Selling price', 'Current stock', 'Low-stock threshold', 'Stock state'] as $heading) {
            $response->assertSee($heading);
        }
        $response->assertSeeInOrder(['Fractional Low', 'Series F', '4 mm', 'kg', 'fractional', '91.07', '0.125', '0.125', 'Low stock', 'active'])
            ->assertSeeInOrder(['Whole Good', 'Series G', '5 mm', 'sheet', 'whole', '101.30', '>10<', '>9<', 'In stock', 'archived'], false)
            ->assertSeeInOrder(['Whole Zero', 'Series W', '3 mm', 'piece', 'whole', '78.42', '>0<', '>2<', 'Out of stock', 'active'], false)
            ->assertDontSee('Cost price')->assertDontSee('9123.45')->assertDontSee('8123.45')->assertDontSee('7123.45');
    }

    public function test_staff_detail_does_not_expose_variant_cost(): void
    {
        $product = $this->product($this->category());
        $this->variant($product, ['cost_price' => '9192.43']);

        $this->actingAs(User::factory()->create())->get(route('products.show', $product))
            ->assertOk()->assertDontSee('Cost price')->assertDontSee('9192.43');
    }

    public function test_empty_permitted_variant_list_has_an_empty_state(): void
    {
        $product = $this->product($this->category());
        $this->variant($product, ['status' => ProductVariant::STATUS_ARCHIVED]);

        $this->actingAs(User::factory()->create())->get(route('products.show', $product))
            ->assertOk()->assertSee('No variants available.')->assertDontSee('Grade 8.8');
    }

    public function test_variants_are_ordered_by_all_identity_columns_then_id(): void
    {
        $product = $this->product($this->category());
        foreach ([
            ['Z', 'A', 'A', 'kg'],
            ['A', 'Z', 'A', 'kg'],
            ['A', 'A', 'Z', 'kg'],
            ['A', 'A', 'A', 'roll'],
            ['A', 'A', 'A', 'piece'],
        ] as [$size, $series, $thickness, $unit]) {
            $this->variant($product, ['size' => $size, 'type_series' => $series, 'thickness' => $thickness, 'unit' => $unit]);
        }

        $variantSelects = [];
        DB::listen(function (QueryExecuted $query) use (&$variantSelects): void {
            if (str_contains($query->sql, 'from "product_variants"')) {
                $variantSelects[] = $query->sql;
            }
        });

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('products.show', $product))->assertOk();
        $this->assertMatchesRegularExpression('/order by "size" asc, "type_series" asc, "thickness" asc, "unit" asc, "id" asc/', $variantSelects[0]);
        $response->assertSeeInOrder([
            '<td class="px-3 py-3">piece</td>',
            '<td class="px-3 py-3">roll</td>',
            '<td class="px-3 py-3">Z</td>',
            '<td class="px-3 py-3">Z</td>',
            '<td class="px-3 py-3">Z</td>',
        ], false);
    }

    public function test_product_list_links_to_detail_for_admin_and_staff(): void
    {
        $product = $this->product($this->category());

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('products.index', ['search' => 'Machine']))
                ->assertOk()->assertSee('href="'.route('products.show', $product).'"', false);
        }
    }

    public function test_detail_is_read_only_and_query_count_is_bounded_for_many_variants(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        for ($i = 0; $i < 12; $i++) {
            $this->variant($product, ['size' => 'Size '.$i, 'current_stock' => '1.000']);
        }
        $beforeProduct = $product->fresh()->getAttributes();
        $beforeVariants = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->get()->map->getAttributes()->all();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->get(route('products.show', $product))->assertOk();

        $reads = array_filter($queries, fn (string $sql) => str_starts_with(strtolower($sql), 'select'));
        $this->assertLessThanOrEqual(6, count($reads));
        $this->assertSame(count($queries), count($reads));
        $this->assertSame($beforeProduct, $product->fresh()->getAttributes());
        $this->assertSame($beforeVariants, ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }
}
