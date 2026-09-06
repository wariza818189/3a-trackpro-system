<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;

class CatalogAuthorizationTest extends CatalogTestCase
{
    public function test_guest_cannot_browse_catalog(): void
    {
        foreach (['/categories', '/products', '/product-variants'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_staff_sees_only_active_hierarchy_without_cost_or_admin_controls(): void
    {
        $staff = User::factory()->create();
        $activeCategory = $this->category();
        $activeProduct = $this->product($activeCategory);
        $this->variant($activeProduct, ['cost_price' => '9182.43']);
        $archivedCategory = $this->category(['name' => 'Hidden Category', 'status' => Category::STATUS_ARCHIVED]);
        $hiddenProduct = $this->product($archivedCategory, ['name' => 'Hidden Product']);
        $this->variant($hiddenProduct, ['size' => 'Hidden Variant']);

        $this->actingAs($staff)->get('/categories')->assertOk()->assertSee('Fasteners')->assertDontSee('Hidden Category')->assertDontSee('Create category');
        $this->actingAs($staff)->get('/products')->assertOk()->assertSee('Machine Bolt')->assertDontSee('Hidden Product')->assertDontSee('Add variant');
        $this->actingAs($staff)->get('/product-variants')->assertOk()->assertSee('M8')->assertDontSee('Hidden Variant')->assertDontSee('9182.43')->assertDontSee('Cost price')->assertDontSee('Archive');
    }

    public function test_staff_direct_mutations_are_forbidden(): void
    {
        $staff = User::factory()->create();
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product);
        $requests = [
            ['get', route('categories.create'), []],
            ['post', route('categories.store'), ['name' => 'Tools']],
            ['patch', route('categories.update', $category), ['name' => 'Tools']],
            ['patch', route('categories.archive', $category), []],
            ['patch', route('categories.reactivate', $category), []],
            ['post', route('products.store', $category), ['name' => 'Nut']],
            ['patch', route('products.update', $product), ['name' => 'Nut', 'category_id' => $category->id]],
            ['patch', route('products.archive', $product), []],
            ['patch', route('products.reactivate', $product), []],
            ['post', route('product-variants.store', $product), $this->validVariant(['size' => 'M10'])],
            ['patch', route('product-variants.update', $variant), $this->validVariant()],
            ['patch', route('product-variants.archive', $variant), []],
            ['patch', route('product-variants.reactivate', $variant), []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $this->actingAs($staff)->{$method}($uri, $data)->assertForbidden();
        }
    }

    public function test_admin_can_open_management_forms_and_disabled_user_is_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $disabled = User::factory()->disabled()->create();
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product);

        foreach ([route('categories.create'), route('categories.edit', $category), route('products.create', $category), route('products.edit', $product), route('product-variants.create', $product), route('product-variants.edit', $variant)] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        $this->actingAs($disabled)->get('/categories')->assertRedirect('/login');
        $this->actingAs($disabled)->post(route('categories.store'), ['name' => 'Tools'])->assertRedirect('/login');
    }
}
