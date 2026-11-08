<?php

namespace App\Policies;

use App\Models\MonitoringPoint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MonitoringPointPolicy
{
    public function view(User $user, MonitoringPoint $point): Response
    {
        return $this->owner($user, $point);
    }

    public function update(User $user, MonitoringPoint $point): Response
    {
        return $this->owner($user, $point);
    }

    public function delete(User $user, MonitoringPoint $point): Response
    {
        return $this->owner($user, $point);
    }

    // Other residents get a 404, so they can't even confirm the point exists
    private function owner(User $user, MonitoringPoint $point): Response
    {
        return $point->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
