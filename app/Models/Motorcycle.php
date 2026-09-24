<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Motorcycle extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'plate',
        'brand',
        'model',
        'year',
        'color',
        'engine_number',
        'chassis_number',
        'vin',
        'engine_displacement',
        'mileage',
        'photo',
        'vehicle_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'engine_displacement' => 'integer',
            'mileage' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeInOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->whereHas('person.memberships', function (Builder $memberships) use ($organizationId): void {
            $memberships->where('organization_id', $organizationId);
        });
    }

    public function belongsToOrganization(string $organizationId): bool
    {
        return $this->person?->belongsToOrganization($organizationId) ?? false;
    }
}
