<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtraction extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'document_scan_id',
        'raw_text',
        'extracted_data',
        'confidence',
        'provider',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'confidence' => 'float',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DocumentScan::class, 'document_scan_id');
    }
}
