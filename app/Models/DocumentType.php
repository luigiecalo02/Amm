<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'entity_type',
        'description',
        'icon',
        'color',
        'requires_expiration',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'requires_expiration' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isForPerson(): bool
    {
        return $this->entity_type === 'person';
    }

    public function isForMotorcycle(): bool
    {
        return $this->entity_type === 'motorcycle';
    }
}
