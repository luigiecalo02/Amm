<?php

namespace App\Models;

use App\Enums\DocumentScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentScan extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'document_id',
        'document_type_id',
        'original_file',
        'status',
        'provider',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentScanStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function extraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class);
    }
}
