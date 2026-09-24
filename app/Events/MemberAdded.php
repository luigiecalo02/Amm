<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OrganizationMember $member,
        public Organization $organization,
        public ?User $actor = null,
    ) {
    }
}
