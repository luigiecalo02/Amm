<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'document_type_id',
        'person_id',
        'motorcycle_id',
        'document_number',
        'issue_date',
        'expiration_date',
        'status',
        'metadata',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiration_date' => 'date',
            'verified_at' => 'datetime',
            'metadata' => 'array',
            'status' => DocumentStatus::class,
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DocumentScan::class);
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->isPast();
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeInOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where(function (Builder $inner) use ($organizationId): void {
            $inner->whereHas('person.memberships', function (Builder $memberships) use ($organizationId): void {
                $memberships->where('organization_id', $organizationId);
            })->orWhereHas('motorcycle.person.memberships', function (Builder $memberships) use ($organizationId): void {
                $memberships->where('organization_id', $organizationId);
            });
        });
    }

    public function belongsToOrganization(string $organizationId): bool
    {
        if ($this->person_id && $this->person?->belongsToOrganization($organizationId)) {
            return true;
        }

        return $this->motorcycle?->belongsToOrganization($organizationId) ?? false;
    }
}
