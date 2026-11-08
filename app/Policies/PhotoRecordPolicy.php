<?php

namespace App\Policies;

use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PhotoRecordPolicy
{
    public function view(User $user, PhotoRecord $record): Response
    {
        return $this->owner($user, $record);
    }

    public function delete(User $user, PhotoRecord $record): Response
    {
        return $this->owner($user, $record);
    }

    // Editing the resident's own note on a photo
    public function update(User $user, PhotoRecord $record): Response
    {
        return $this->owner($user, $record);
    }

    public function analyze(User $user, PhotoRecord $record): Response
    {
        return $this->owner($user, $record);
    }

    // Other residents get a 404, so they can't even confirm the record exists
    private function owner(User $user, PhotoRecord $record): Response
    {
        return $record->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
