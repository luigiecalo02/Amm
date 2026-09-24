<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAssignedToOrganization
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public Organization $organization,
        public ?User $actor = null,
    ) {
    }
}
