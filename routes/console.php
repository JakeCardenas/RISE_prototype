<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The evaluation workspace opens only for accounts named here; there is no form for it
Artisan::command('rise:researcher {email} {--revoke}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if (! $user) {
        $this->error('No account uses that email address.');

        return 1;
    }

    $user->forceFill(['is_researcher' => ! $this->option('revoke')])->save();
    $this->info($this->option('revoke') ? 'Evaluation workspace access removed.' : 'Evaluation workspace access granted.');

    return 0;
})->purpose('Grant or remove access to the RISE evaluation workspace');
