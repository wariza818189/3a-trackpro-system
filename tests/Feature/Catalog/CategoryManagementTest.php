<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;

class CategoryManagementTest extends CatalogTestCase
{
    public function test_admin_creates_normalized_active_category_and_blank_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('categories.store'), ['name' => "  Power\n  Tools  "])
            ->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Power Tools', 'status' => Category::STATUS_ACTIVE]);

        $this->actingAs($admin)->post(route('categories.store'), ['name' => '   '])
            ->assertSessionHasErrors('name');
    }

    public function test_case_and_whitespace_normalized_duplicate_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->category(['name' => 'Power Tools']);

        $this->actingAs($admin)->post(route('categories.store'), ['name' => '  power   tools  '])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Category::query()->count());
    }

    public function test_active_category_can_be_updated_archived_and_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();

        $this->actingAs($admin)->patch(route('categories.update', $category), ['name' => '  Fixing   Supplies '])
            ->assertRedirect(route('categories.index'));
        $this->assertSame('Fixing Supplies', $category->fresh()->name);

        $this->actingAs($admin)->patch(route('categories.archive', $category))->assertSessionHasNoErrors();
        $this->assertSame(Category::STATUS_ARCHIVED, $category->fresh()->status);
        $this->actingAs($admin)->get(route('categories.edit', $category))->assertStatus(409);
        $this->actingAs($admin)->patch(route('categories.update', $category), ['name' => 'Not Allowed'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->patch(route('categories.reactivate', $category))->assertSessionHasNoErrors();
        $this->assertSame(Category::STATUS_ACTIVE, $category->fresh()->status);
        $this->actingAs($admin)->patch(route('categories.reactivate', $category))->assertSessionHasErrors('status');
    }

    public function test_category_archive_is_denied_while_it_has_an_active_product(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();
        $this->product($category);

        $this->actingAs($admin)->patch(route('categories.archive', $category))->assertSessionHasErrors('status');
        $this->assertSame(Category::STATUS_ACTIVE, $category->fresh()->status);
    }

    public function test_category_has_no_delete_route(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->category();

        $this->actingAs($admin)->delete('/categories/'.$category->id)->assertMethodNotAllowed();
    }
}
