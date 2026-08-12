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
            || ($user->can('view service requests') && $this->sameDepartment($user, $serviceRequest));
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

    public function review(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can('review service requests') && $this->sameDepartment($user, $serviceRequest);
    }

    public function manage(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can('manage service requests') && $this->sameDepartment($user, $serviceRequest);
    }

    private function sameDepartment(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->department_id === null
            || $user->departments()->whereKey($serviceRequest->department_id)->exists();
    }
}
