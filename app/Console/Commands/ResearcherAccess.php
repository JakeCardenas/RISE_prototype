<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

// The evaluation workspace opens only for accounts named here; there is no form for it
class ResearcherAccess extends Command
{
    protected $signature = 'rise:researcher {email} {--revoke}';

    protected $description = 'Grant or remove access to the RISE evaluation workspace';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_researcher' => ! $this->option('revoke')])->save();
        $this->info($this->option('revoke') ? 'Evaluation workspace access removed.' : 'Evaluation workspace access granted.');

        return self::SUCCESS;
    }
}
