<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Valid = 'valid';
    case Expired = 'expired';
    case PendingReview = 'pending_review';
    case Revoked = 'revoked';
}
