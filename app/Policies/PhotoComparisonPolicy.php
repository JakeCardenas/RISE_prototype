<?php

namespace App\Policies;

use App\Models\PhotoComparison;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PhotoComparisonPolicy
{
    public function view(User $user, PhotoComparison $comparison): Response
    {
        return $this->owner($user, $comparison);
    }

    public function update(User $user, PhotoComparison $comparison): Response
    {
        return $this->owner($user, $comparison);
    }

    // Other residents get a 404, so they can't even confirm the comparison exists
    private function owner(User $user, PhotoComparison $comparison): Response
    {
        return $comparison->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
