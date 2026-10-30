<?php

namespace App\Enums;

enum InscriptionApplicationStatus: string
{
    case Draft = 'draft';
    case ReadyForForm = 'ready_for_form';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case AccessGranted = 'access_granted';
    case Rejected = 'rejected';
}
