<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->requester_id === $user->id
            || $serviceRequest->assigned_to === $user->id
            || $user->can('view service requests');
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->requester_id === $user->id
            && $serviceRequest->status->value === 'revision_requested';
    }

    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->requester_id === $user->id
            && in_array($serviceRequest->status->value, ['submitted', 'under_review', 'revision_requested'], true);
    }
}
