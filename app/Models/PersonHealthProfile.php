<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonHealthProfile extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'person_id',
        'eps',
        'blood_type',
        'rh',
        'weight_kg',
        'height_cm',
        'special_treatment',
        'medications',
        'allergies',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
