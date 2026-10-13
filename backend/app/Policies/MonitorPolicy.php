<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Monitor;
use Illuminate\Auth\Access\Response;

class MonitorPolicy
{
    public function create(User $user): Response
    {
        $plan = $user->plan();

        return $user->monitors()->count() < $plan->maxMonitors()
            ? Response::allow()
            : Response::deny("Your {$plan->value} allows up to {$plan->maxMonitors()} monitors. Delete one.");
    }

    public function view(User $user, Monitor $monitor): Response
    {
        return $this->owns($user, $monitor);
    }
 
    public function update(User $user, Monitor $monitor): Response
    {
        return $this->owns($user, $monitor);
    }
 
    public function delete(User $user, Monitor $monitor): Response
    {
        return $this->owns($user, $monitor);
    }

    public function resume(User $user, Monitor $monitor): Response
    {
        $owns = $this->owns($user, $monitor);
 
        if ($owns->denied()) {
            return $owns;
        }
 
        $plan = $user->plan();
        $active = $user->monitors()->where('is_active', true)->count();
 
        return $active < $plan->maxMonitors()
            ? Response::allow()
            : Response::deny("Your {$plan->value} plan allows up to {$plan->maxMonitors()} active monitors. Pause or delete another monitor first.");
    }

    private function owns(User $user, Monitor $monitor): Response
    {
        return $user->id === $monitor->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
