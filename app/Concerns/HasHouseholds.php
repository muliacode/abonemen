<?php

namespace App\Concerns;

use App\Data\UserHousehold;
use App\Enums\HouseholdPermission;
use App\Enums\HouseholdRole;
use App\Enums\PermissionScope;
use App\Models\Household;
use App\Models\Member;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

trait HasHouseholds
{
    use HasRoles;

    /**
     * Get all of the households the user belongs to, through their members.
     *
     * @return BelongsToMany<Household, $this>
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'members', 'user_id', 'household_id')
            ->withTimestamps();
    }

    /**
     * Get the user's member in each household they belong to.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'user_id');
    }

    /**
     * Get the user's current household.
     *
     * @return BelongsTo<Household, $this>
     */
    public function currentHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'current_household_id');
    }

    /**
     * Switch to the given household.
     */
    public function switchHousehold(Household $household): bool
    {
        if (! $this->belongsToHousehold($household)) {
            return false;
        }

        $this->update(['current_household_id' => $household->id]);
        $this->setRelation('currentHousehold', $household);

        URL::defaults(['current_household' => $household->ulid]);

        return true;
    }

    /**
     * Get the page the user lands on: their current household's dashboard,
     * or the households page when they do not belong to one yet.
     */
    public function homeUrl(): string
    {
        return $this->currentHousehold
            ? route('dashboard', ['current_household' => $this->currentHousehold->ulid])
            : route('households.index');
    }

    /**
     * Determine if the user belongs to the given household.
     */
    public function belongsToHousehold(Household $household): bool
    {
        return $this->members()->where('household_id', $household->id)->exists();
    }

    /**
     * Determine if the given household is the user's current household.
     */
    public function isCurrentHousehold(Household $household): bool
    {
        return $this->current_household_id === $household->id;
    }

    /**
     * Get the user's role in the given household.
     */
    public function householdRole(Household $household): ?HouseholdRole
    {
        return $this->inHousehold($household, function () {
            foreach (HouseholdRole::cases() as $role) {
                if ($this->hasRole($role->value)) {
                    return $role;
                }
            }

            return null;
        });
    }

    /**
     * Get the scopes at which the user holds the given permission in the household.
     *
     * @return list<PermissionScope>
     */
    public function householdPermissionScopes(Household $household, HouseholdPermission $permission): array
    {
        return $this->inHousehold($household, fn () => array_values(array_filter(
            PermissionScope::cases(),
            fn (PermissionScope $scope) => $this->checkPermissionTo($permission->named($scope)),
        )));
    }

    /**
     * Determine if the user holds the given permission in the household.
     *
     * Pass a scope for a scoped permission such as "data.write"; leave it out
     * for permissions that have none, such as "household.manage".
     */
    public function hasHouseholdPermission(Household $household, HouseholdPermission $permission, ?PermissionScope $scope = null): bool
    {
        return $this->inHousehold($household, fn () => $this->checkPermissionTo($permission->named($scope)));
    }

    /**
     * Give the user a role in the given household.
     */
    public function assignHouseholdRole(Household $household, HouseholdRole|string $role): void
    {
        $name = $role instanceof HouseholdRole ? $role->value : $role;

        $this->inHousehold($household, fn () => $this->assignRole($name));
    }

    /**
     * Get the user's households as a collection of UserHousehold objects.
     *
     * @return Collection<int, UserHousehold>
     */
    public function toUserHouseholds(): Collection
    {
        return $this->households()
            ->orderByRaw('LOWER(households.name)')
            ->get()
            ->map(fn (Household $household) => $this->toUserHousehold($household))
            ->values();
    }

    /**
     * Get the given household as a UserHousehold object.
     */
    public function toUserHousehold(Household $household): UserHousehold
    {
        $role = $this->householdRole($household);

        return new UserHousehold(
            id: $household->id,
            ulid: $household->ulid,
            name: $household->name,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentHousehold($household),
        );
    }

    /**
     * Get another household to fall back to, if the user belongs to one.
     */
    public function fallbackHousehold(?Household $excluding = null): ?Household
    {
        return $this->households()
            ->when($excluding, fn ($query) => $query->where('households.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(households.name)')
            ->first();
    }

    /**
     * Run the callback with the permission team set to the given household.
     *
     * Roles are held per household, so the cached role and permission
     * relations must not leak from one household to another.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function inHousehold(Household $household, Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($household->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
