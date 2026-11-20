<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExpirationNotice extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'document_id',
        'expiration_date',
        'last_sent_on',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'last_sent_on' => 'date',
            'source_updated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
