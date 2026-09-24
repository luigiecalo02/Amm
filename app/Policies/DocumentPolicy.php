<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('documents.view', $organization);
    }

    public function view(User $user, Document $document, Organization $organization): bool
    {
        return $document->belongsToOrganization($organization->id)
            && $user->hasPermission('documents.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('documents.create', $organization);
    }

    public function update(User $user, Document $document, Organization $organization): bool
    {
        return $document->belongsToOrganization($organization->id)
            && $user->hasPermission('documents.update', $organization);
    }

    public function delete(User $user, Document $document, Organization $organization): bool
    {
        return $document->belongsToOrganization($organization->id)
            && $user->hasPermission('documents.delete', $organization);
    }

    public function download(User $user, Document $document, Organization $organization): bool
    {
        return $document->belongsToOrganization($organization->id)
            && $user->hasPermission('documents.download', $organization);
    }

    public function scan(User $user, Organization $organization): bool
    {
        return $user->hasPermission('documents.scan', $organization);
    }
}
