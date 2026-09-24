<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'logo',
        'description',
        'email',
        'phone',
        'country',
        'department',
        'city',
        'address',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'settings' => 'array',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function people(): HasManyThrough
    {
        return $this->hasManyThrough(
            Person::class,
            OrganizationMember::class,
            'organization_id',
            'id',
            'id',
            'person_id'
        );
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(OrganizationUserRole::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
