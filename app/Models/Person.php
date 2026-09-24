<?php

namespace App\Models;

use App\Enums\PersonStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'people';

    protected $fillable = [
        'document_type',
        'document_number',
        'document_issued_in',
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'birth_date',
        'birth_city',
        'birth_department',
        'gender',
        'marital_status',
        'phone',
        'email',
        'address',
        'neighborhood',
        'city',
        'department',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => PersonStatus::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->second_last_name,
        ])->filter()->implode(' ');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function motorcycles(): HasMany
    {
        return $this->hasMany(Motorcycle::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function healthProfile(): HasOne
    {
        return $this->hasOne(PersonHealthProfile::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function scopeInOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->whereHas('memberships', function (Builder $memberships) use ($organizationId): void {
            $memberships->where('organization_id', $organizationId);
        });
    }

    public function belongsToOrganization(string $organizationId): bool
    {
        return $this->memberships()
            ->where('organization_id', $organizationId)
            ->exists();
    }
}
