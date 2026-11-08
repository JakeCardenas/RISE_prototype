<?php

namespace App\Policies;

use App\Models\PhotoAnalysis;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PhotoAnalysisPolicy
{
    // Rating a result of the resident's own photo
    public function update(User $user, PhotoAnalysis $analysis): Response
    {
        return $analysis->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
