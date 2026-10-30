<?php

namespace App\Models;

use App\Support\OrganizationContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;

    protected $fillable = [
        'person_id',
        'name',
        'email',
        'google_id',
        'avatar',
        'password',
        'is_platform_admin',
        'current_organization_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizationRoles(): HasMany
    {
        return $this->hasMany(OrganizationUserRole::class);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function belongsToOrganization(Organization|string $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->organizationRoles()
            ->where('organization_id', $organizationId)
            ->exists();
    }

    public function canAccessOrganization(Organization|string $organization): bool
    {
        return $this->isSuperAdmin() || $this->belongsToOrganization($organization);
    }

    public function hasPermission(string $permission, Organization|string|null $organization = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $organizationId = $this->resolveOrganizationId($organization);

        if (! $organizationId) {
            return false;
        }

        return $this->organizationRoles()
            ->where('organization_id', $organizationId)
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    public function permissionSlugs(?Organization $organization = null): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        $organizationId = $this->resolveOrganizationId($organization);

        if (! $organizationId) {
            return [];
        }

        return $this->organizationRoles()
            ->where('organization_id', $organizationId)
            ->with('role.permissions')
            ->get()
            ->flatMap(fn (OrganizationUserRole $assignment) => $assignment->role?->permissions->pluck('slug') ?? collect())
            ->unique()
            ->values()
            ->all();
    }

    public function roleIn(Organization $organization): ?Role
    {
        return $this->organizationRoles()
            ->where('organization_id', $organization->id)
            ->with('role')
            ->first()
            ?->role;
    }

    public function accessibleOrganizations(): Builder
    {
        if ($this->isSuperAdmin()) {
            return Organization::query();
        }

        return Organization::query()->whereIn(
            'id',
            $this->organizationRoles()->select('organization_id')
        );
    }

    public function canSwitchClubs(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->organizationRoles()
            ->select('organization_id')
            ->distinct()
            ->count() > 1;
    }

    private function resolveOrganizationId(Organization|string|null $organization): ?string
    {
        if ($organization instanceof Organization) {
            return $organization->id;
        }

        if (is_string($organization)) {
            return $organization;
        }

        return app(OrganizationContext::class)->id();
    }
}
