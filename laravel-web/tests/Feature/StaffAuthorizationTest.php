<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StaffRole;
use App\Models\StaffRolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Phase 5 authorization regression tests.
class StaffAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function staffWith(array $permissions): array
    {
        $owner = User::factory()->create(['type' => 'admin', 'parent_id' => 0]);
        $role = StaffRole::create([
            'parent_id' => $owner->id,
            'name' => 'Test Role',
            'description' => 'Automated test role',
            'status' => 1,
        ]);

        foreach ($permissions as $permission) {
            StaffRolePermission::create([
                'staff_role_id' => $role->id,
                'permission_key' => $permission,
            ]);
        }

        $staff = User::factory()->create([
            'type' => 'staff',
            'parent_id' => $owner->id,
            'staff_role_id' => $role->id,
        ]);

        return [$owner, $staff];
    }

    public function test_staff_without_product_create_permission_gets_403(): void
    {
        [, $staff] = $this->staffWith(['products.view']);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/products', ['title' => 'Protein', 'price' => 100])
            ->assertForbidden();
    }

    public function test_staff_with_product_create_permission_can_create_for_own_gym(): void
    {
        [$owner, $staff] = $this->staffWith(['products.create']);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/products', ['title' => 'Protein', 'price' => 100])
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'parent_id' => $owner->id,
            'title' => 'Protein',
        ]);
    }

    public function test_staff_cannot_delete_product_without_delete_permission(): void
    {
        [$owner, $staff] = $this->staffWith(['products.view']);
        $product = Product::create([
            'parent_id' => $owner->id,
            'title' => 'Protein',
            'price' => 100,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->deleteJson('/api/v1/products/' . $product->id)
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
