<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Person $person,
        public Organization $organization,
        public ?User $actor = null,
    ) {
    }
}
