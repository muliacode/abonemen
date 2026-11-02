<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdPermission;
use App\Enums\HouseholdRole;
use App\Enums\PermissionScope;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HouseholdPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_manage_the_household_and_change_its_data(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Owner);

        $this->assertTrue(Gate::forUser($user)->allows('update', $household));
        $this->assertTrue(Gate::forUser($user)->allows('writeData', $household));
        $this->assertTrue(Gate::forUser($user)->allows('readData', $household));
    }

    public function test_an_editor_can_change_data_but_cannot_manage_the_household(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Editor);

        $this->assertFalse(Gate::forUser($user)->allows('update', $household));
        $this->assertTrue(Gate::forUser($user)->allows('writeData', $household));
        $this->assertTrue(Gate::forUser($user)->allows('readData', $household));
    }

    public function test_a_viewer_can_only_read_the_household(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Viewer);

        $this->assertFalse(Gate::forUser($user)->allows('update', $household));
        $this->assertFalse(Gate::forUser($user)->allows('writeData', $household));
        $this->assertTrue(Gate::forUser($user)->allows('readData', $household));
    }

    public function test_default_roles_carry_scoped_permissions(): void
    {
        [$owner, $household] = $this->userWithRole(HouseholdRole::Owner);
        [$viewer, $otherHousehold] = $this->userWithRole(HouseholdRole::Viewer);

        $this->assertSame(
            [PermissionScope::All],
            $owner->householdPermissionScopes($household, HouseholdPermission::ReadData),
        );
        $this->assertSame(
            [PermissionScope::Visible],
            $viewer->householdPermissionScopes($otherHousehold, HouseholdPermission::ReadData),
        );
        $this->assertSame(
            [],
            $viewer->householdPermissionScopes($otherHousehold, HouseholdPermission::WriteData),
        );
    }

    public function test_a_household_role_can_be_limited_to_own_subscriptions(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $household->members()->create(['name' => $user->name, 'user_id' => $user->id]);

        $role = Role::create(['name' => 'contributor', 'guard_name' => 'web', 'household_id' => $household->id]);
        $role->givePermissionTo([
            Permission::findOrCreate(HouseholdPermission::ReadData->named(PermissionScope::Visible), 'web'),
            Permission::findOrCreate(HouseholdPermission::WriteData->named(PermissionScope::Own), 'web'),
        ]);
        $user->assignHouseholdRole($household, 'contributor');

        $this->assertSame(
            [PermissionScope::Own],
            $user->householdPermissionScopes($household, HouseholdPermission::WriteData),
        );
        $this->assertTrue($user->hasHouseholdPermission($household, HouseholdPermission::WriteData, PermissionScope::Own));
        $this->assertFalse($user->hasHouseholdPermission($household, HouseholdPermission::WriteData, PermissionScope::All));
        $this->assertTrue(Gate::forUser($user)->allows('writeData', $household));
        $this->assertFalse(Gate::forUser($user)->allows('update', $household));
    }

    public function test_a_role_in_one_household_does_not_apply_in_another(): void
    {
        $user = User::factory()->create();
        $first = Household::factory()->withUser($user, HouseholdRole::Owner)->create();
        $second = Household::factory()->withUser($user, HouseholdRole::Viewer)->create();

        $this->assertTrue(Gate::forUser($user)->allows('update', $first));
        $this->assertFalse(Gate::forUser($user)->allows('update', $second));
        $this->assertFalse(Gate::forUser($user)->allows('writeData', $second));
        $this->assertTrue(Gate::forUser($user)->allows('writeData', $first));
    }

    public function test_someone_outside_a_household_has_no_permissions_in_it(): void
    {
        [, $household] = $this->userWithRole(HouseholdRole::Owner);
        $stranger = User::factory()->create();

        $this->assertFalse(Gate::forUser($stranger)->allows('view', $household));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $household));
        $this->assertFalse(Gate::forUser($stranger)->allows('readData', $household));
        $this->assertFalse(Gate::forUser($stranger)->allows('writeData', $household));
    }

    public function test_a_role_without_a_member_in_the_household_grants_nothing(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Owner);

        $household->members()->where('user_id', $user->id)->delete();

        $this->assertFalse(Gate::forUser($user)->allows('view', $household));
        $this->assertFalse(Gate::forUser($user)->allows('update', $household));
        $this->assertFalse(Gate::forUser($user)->allows('writeData', $household));
        $this->assertNull($user->householdRole($household));
    }

    public function test_the_owner_role_carries_every_ability_at_full_scope(): void
    {
        [$owner, $household] = $this->userWithRole(HouseholdRole::Owner);

        foreach (HouseholdPermission::cases() as $permission) {
            $scope = $permission->isScoped() ? PermissionScope::All : null;

            $this->assertTrue(
                $owner->hasHouseholdPermission($household, $permission, $scope),
                "Owner is missing {$permission->named($scope)}",
            );
        }
    }

    public function test_giving_a_user_a_new_role_replaces_the_old_one(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Owner);

        $user->assignHouseholdRole($household, HouseholdRole::Viewer);

        $this->assertSame(HouseholdRole::Viewer, $user->householdRole($household));
        $this->assertFalse(Gate::forUser($user)->allows('update', $household));
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function userWithRole(HouseholdRole $role): array
    {
        $user = User::factory()->create();
        $household = Household::factory()->withUser($user, $role)->create();

        return [$user, $household];
    }
}
