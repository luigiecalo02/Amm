<?php

namespace App\Models;

use App\Enums\InscriptionApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscriptionApplication extends Model
{
    use HasUlids;

    protected $fillable = [
        'inscription_link_id',
        'organization_id',
        'person_id',
        'access_user_id',
        'verified_by',
        'status',
        'reference',
        'email',
        'extracted_data',
        'form_draft',
        'uploads',
        'submitted_at',
        'verified_at',
        'access_granted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InscriptionApplicationStatus::class,
            'extracted_data' => 'array',
            'form_draft' => 'array',
            'uploads' => 'array',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'access_granted_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(InscriptionLink::class, 'inscription_link_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function accessUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'access_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function canEditForm(): bool
    {
        return in_array($this->status, [
            InscriptionApplicationStatus::Draft,
            InscriptionApplicationStatus::ReadyForForm,
        ], true);
    }

    public function isPendingReview(): bool
    {
        return $this->status === InscriptionApplicationStatus::Submitted;
    }
}
