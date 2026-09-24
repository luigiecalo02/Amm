<?php

namespace App\Enums;

enum DocumentScanStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';
}
