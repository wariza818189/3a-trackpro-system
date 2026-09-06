<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

class CatalogRouteSecurityTest extends CatalogTestCase
{
    public function test_catalog_routes_have_required_server_side_middleware(): void
    {
        app(Kernel::class);

        foreach (['categories.index', 'products.index', 'product-variants.index'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
        }

        foreach ([
            'categories.create', 'categories.store', 'categories.edit', 'categories.update', 'categories.archive', 'categories.reactivate',
            'products.create', 'products.store', 'products.edit', 'products.update', 'products.archive', 'products.reactivate',
            'product-variants.create', 'product-variants.store', 'product-variants.edit', 'product-variants.update', 'product-variants.archive', 'product-variants.reactivate',
        ] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('can:access-admin', $middleware);
        }
    }

    public function test_state_changes_are_not_get_or_delete_routes(): void
    {
        foreach ([
            'categories.store', 'categories.update', 'categories.archive', 'categories.reactivate',
            'products.store', 'products.update', 'products.archive', 'products.reactivate',
            'product-variants.store', 'product-variants.update', 'product-variants.archive', 'product-variants.reactivate',
        ] as $name) {
            $methods = Route::getRoutes()->getByName($name)->methods();
            $this->assertNotContains('GET', $methods);
            $this->assertNotContains('DELETE', $methods);
            $this->assertContains(str_ends_with($name, '.store') ? 'POST' : 'PATCH', $methods);
        }
    }

    public function test_admin_forms_include_csrf_and_csrf_has_no_exclusions(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product);

        foreach ([
            route('categories.create'), route('categories.edit', $category),
            route('products.create', $category), route('products.edit', $product),
            route('product-variants.create', $product), route('product-variants.edit', $variant),
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk()->assertSee('name="_token"', false);
        }

        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertSame([], app(PreventRequestForgery::class)->getExcludedPaths());
    }

    public function test_no_setup_debug_or_bootstrap_routes_were_added(): void
    {
        foreach (['/setup', '/migrate', '/seed', '/reset-admin', '/debug-users', '/trackpro/create-admin'] as $path) {
            $this->get($path)->assertNotFound();
            $this->post($path)->assertNotFound();
        }
    }
}
