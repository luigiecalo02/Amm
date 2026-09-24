<?php

namespace App\Events;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentDownloaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Document $document,
        public Organization $organization,
        public ?User $actor = null,
    ) {
    }
}
