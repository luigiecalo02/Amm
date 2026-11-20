<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'document_alerts',
        'birthday_alerts',
    ];

    protected function casts(): array
    {
        return [
            'document_alerts' => 'boolean',
            'birthday_alerts' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
