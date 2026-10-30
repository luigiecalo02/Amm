<?php

namespace App\Enums;

enum PersonStatus: string
{
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Inactive = 'inactive';
}
