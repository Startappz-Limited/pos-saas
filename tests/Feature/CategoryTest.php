<?php

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create permissions
    Permission::create(['name' => 'categories.view']);
    Permission::create(['name' => 'categories.create']);
    Permission::create(['name' => 'categories.update']);
    Permission::create(['name' => 'categories.delete']);

    // Assign permissions to user
    $role = Role::create(['name' => 'admin']);
    $role->givePermissionTo(['categories.view', 'categories.create', 'categories.update', 'categories.delete']);
    $this->user->assignRole($role);
});

// Authorization Tests
test('authorized user can view categories', function () {
    $response = $this->get(route('categories.index'));
    $response->assertOk();
});

test('unauthorized user cannot view categories', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('categories.index'));
    $response->assertForbidden();
});

test('authorized user can create category', function () {
    $response = $this->post(route('categories.store'), [
        'name' => 'Test Category',
        'description' => 'Test description',
        'order' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'name' => 'Test Category',
        'slug' => 'test-category',
    ]);
});

test('unauthorized user cannot create category', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categories.store'), [
        'name' => 'Test Category',
    ]);

    $response->assertForbidden();
});

// Validation Tests
test('category name is required', function () {
    $response = $this->post(route('categories.store'), [
        'description' => 'Test description',
    ]);

    $response->assertSessionHasErrors('name');
});

test('category name must be unique', function () {
    Category::factory()->create(['name' => 'Duplicate Category']);

    $response = $this->post(route('categories.store'), [
        'name' => 'Duplicate Category',
    ]);

    $response->assertSessionHasErrors('name');
});

test('category slug must be unique', function () {
    Category::factory()->create(['slug' => 'duplicate-slug']);

    $response = $this->post(route('categories.store'), [
        'name' => 'Test Category',
        'slug' => 'duplicate-slug',
    ]);

    $response->assertSessionHasErrors('slug');
});

test('category slug must be lowercase with hyphens', function () {
    $response = $this->post(route('categories.store'), [
        'name' => 'Test Category',
        'slug' => 'Invalid Slug!',
    ]);

    $response->assertSessionHasErrors('slug');
});

test('parent category must exist', function () {
    $response = $this->post(route('categories.store'), [
        'name' => 'Test Category',
        'parent_id' => 999,
    ]);

    $response->assertSessionHasErrors('parent_id');
});

test('category cannot be its own parent on update', function () {
    $category = Category::factory()->create();

    $response = $this->put(route('categories.update', $category), [
        'name' => 'Updated Category',
        'parent_id' => $category->id,
    ]);

    $response->assertSessionHasErrors('parent_id');
});

// CRUD Tests
test('can update category', function () {
    $category = Category::factory()->create(['name' => 'Original Name']);

    $response = $this->put(route('categories.update', $category), [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Updated Name',
        'slug' => 'updated-name',
    ]);
});

test('can delete category', function () {
    $category = Category::factory()->create();

    $response = $this->delete(route('categories.destroy', $category));

    $response->assertRedirect();
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

test('can activate category', function () {
    $category = Category::factory()->inactive()->create();

    $response = $this->post(route('categories.activate', $category));

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'status' => CategoryStatus::ACTIVE->value,
    ]);
});

test('can deactivate category', function () {
    $category = Category::factory()->active()->create();

    $response = $this->post(route('categories.deactivate', $category));

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'status' => CategoryStatus::INACTIVE->value,
    ]);
});

// Hierarchical Tests
test('can create category with parent', function () {
    $parentCategory = Category::factory()->create(['name' => 'Parent Category']);

    $response = $this->post(route('categories.store'), [
        'name' => 'Child Category',
        'parent_id' => $parentCategory->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'name' => 'Child Category',
        'parent_id' => $parentCategory->id,
    ]);
});

test('children are reassigned to grandparent when parent is deleted', function () {
    $grandparent = Category::factory()->create(['name' => 'Grandparent']);
    $parent = Category::factory()->create(['name' => 'Parent', 'parent_id' => $grandparent->id]);
    $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

    $this->delete(route('categories.destroy', $parent));

    $child->refresh();
    expect($child->parent_id)->toBe($grandparent->id);
});

test('category can retrieve all descendants', function () {
    $parent = Category::factory()->create();
    $child1 = Category::factory()->withParent($parent)->create();
    $child2 = Category::factory()->withParent($parent)->create();
    $grandchild = Category::factory()->withParent($child1)->create();

    $parent->load('children.children');

    // children has no defined ordering, so first() is not necessarily $child1 —
    // assert against the specific child that owns the grandchild.
    expect($parent->children)->toHaveCount(2)
        ->and($parent->children->firstWhere('id', $child1->id)->children)->toHaveCount(1)
        ->and($parent->children->firstWhere('id', $child2->id)->children)->toHaveCount(0);
});

// Model Tests
test('category factory creates valid category', function () {
    $category = Category::factory()->create();

    expect($category->uuid)->not()->toBeEmpty()
        ->and($category->name)->not()->toBeEmpty()
        ->and($category->slug)->not()->toBeEmpty()
        ->and($category->status)->toBe(CategoryStatus::ACTIVE);
});

test('category inactive state works', function () {
    $category = Category::factory()->inactive()->create();

    expect($category->status)->toBe(CategoryStatus::INACTIVE)
        ->and($category->isActive())->toBeFalse();
});

test('category with parent state works', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->withParent($parent)->create();

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->hasParent())->toBeTrue();
});

// Helper Method Tests
test('isActive method returns correct boolean', function () {
    $activeCategory = Category::factory()->active()->create();
    $inactiveCategory = Category::factory()->inactive()->create();

    expect($activeCategory->isActive())->toBeTrue()
        ->and($inactiveCategory->isActive())->toBeFalse();
});

test('isParent method returns correct boolean', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->withParent($parent)->create();

    expect($parent->isParent())->toBeTrue()
        ->and($child->isParent())->toBeFalse();
});

test('hasParent method returns correct boolean', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->withParent($parent)->create();

    expect($parent->hasParent())->toBeFalse()
        ->and($child->hasParent())->toBeTrue();
});

test('getFullPathAttribute returns correct path', function () {
    $grandparent = Category::factory()->create(['name' => 'Grandparent']);
    $parent = Category::factory()->create(['name' => 'Parent', 'parent_id' => $grandparent->id]);
    $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

    expect($child->full_path)->toBe('Grandparent > Parent > Child');
});

// Scope Tests
test('active scope returns only active categories', function () {
    Category::factory()->active()->count(3)->create();
    Category::factory()->inactive()->count(2)->create();

    $activeCategories = Category::active()->get();

    expect($activeCategories)->toHaveCount(3);
});

test('inactive scope returns only inactive categories', function () {
    Category::factory()->active()->count(3)->create();
    Category::factory()->inactive()->count(2)->create();

    $inactiveCategories = Category::inactive()->get();

    expect($inactiveCategories)->toHaveCount(2);
});

test('rootCategories scope returns only root categories', function () {
    $root1 = Category::factory()->create();
    $root2 = Category::factory()->create();
    Category::factory()->withParent($root1)->create();
    Category::factory()->withParent($root2)->create();

    $rootCategories = Category::rootCategories()->get();

    expect($rootCategories)->toHaveCount(2);
});

test('ordered scope returns categories by order', function () {
    Category::factory()->create(['order' => 3]);
    Category::factory()->create(['order' => 1]);
    Category::factory()->create(['order' => 2]);

    $categories = Category::ordered()->get();

    expect($categories->first()->order)->toBe(1)
        ->and($categories->last()->order)->toBe(3);
});
