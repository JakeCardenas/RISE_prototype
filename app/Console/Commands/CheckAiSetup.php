<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzePhoto;
use App\Models\PhotoAnalysis;
use App\Services\Analysis\AnalysisProviders;
use App\Services\Comparison\PhotoComparer;
use App\Services\Gemini\GeminiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

// The AI setup at a glance, for the person who runs RISE. It never shows the key, headers or anything Gemini sends
// back. With --ping it asks the configured Gemini model for a one-word text reply, with no photo, to see whether it
// answers at all.
class CheckAiSetup extends Command
{
    protected $signature = 'rise:ai-check {--ping}';

    protected $description = 'Check the AI analysis setup without showing secrets (--ping asks Gemini for a short text reply)';

    public function handle(AnalysisProviders $providers, PhotoComparer $comparer): int
    {
        $detector = $providers->default();
        $gemini = new GeminiClient(config('services.gemini'));
        $timeout = $gemini->timeout();
        $jobTimeout = (new ReflectionClass(AnalyzePhoto::class))->getDefaultProperties()['timeout'];
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 90);
        // A slow first attempt is never retried, so the longest Gemini call is the 15-second retry window, 3 seconds of
        // waiting, then one last full attempt
        $longest = $timeout + 18;

        $this->newLine();
        $this->line('  <options=bold>Photo analysis</>');
        $this->components->twoColumnDetail('Provider residents get', AnalysisProviders::label($detector->name()));
        $this->components->twoColumnDetail('Set up', $detector->isConfigured() ? '<fg=green>yes</>' : '<fg=red>no: set '.implode(' and ', $detector->missingSettings()).' in .env</>');
        $this->components->twoColumnDetail('Model', $detector->modelId() ?? '—');

        $this->line('  <options=bold>Gemini</>');
        $this->components->twoColumnDetail('GEMINI_API_KEY', filled(config('services.gemini.api_key')) ? 'set (not shown)' : '<fg=red>missing</>');
        $this->components->twoColumnDetail('GEMINI_MODEL', $gemini->modelId() ?? '<fg=red>missing</>');
        $this->components->twoColumnDetail('API URL and revision', config('services.gemini.api_url').' · '.(config('services.gemini.api_revision') ?: 'none'));
        $this->components->twoColumnDetail('Thinking level', config('services.gemini.thinking_level') ?: 'model default');

        $this->line('  <options=bold>Comparison</>');
        $this->components->twoColumnDetail('Provider', $comparer->isConfigured() ? $comparer->name().' · '.$comparer->modelId() : 'not set up');

        $this->line('  <options=bold>Queue and time limits</>');
        $this->components->twoColumnDetail('Queue connection', config('queue.default'));
        $chain = "Gemini {$timeout} s (up to {$longest} s with retries) < job {$jobTimeout} s < queue retry {$retryAfter} s";
        $this->components->twoColumnDetail($chain, $longest < $jobTimeout && $jobTimeout < $retryAfter ? '<fg=green>OK</>' : '<fg=red>lower GEMINI_TIMEOUT</>');

        if (config('queue.default') === 'database') {
            $oldest = DB::table('jobs')->min('created_at');
            $this->components->twoColumnDetail('Jobs waiting', DB::table('jobs')->count().($oldest && now()->timestamp - $oldest > 120 ? ' <fg=yellow>(waiting over 2 minutes: is a worker running?)</>' : ''));
            $this->components->twoColumnDetail('Failed jobs', (string) DB::table('failed_jobs')->count());
        }

        $this->line('  <options=bold>Latest analyses</>');
        $recent = PhotoAnalysis::query()->latest('requested_at')->limit(5)->get();
        foreach ($recent as $analysis) {
            $took = $analysis->started_at && $analysis->completed_at ? ' · '.$analysis->started_at->diffInSeconds($analysis->completed_at, true).' s' : '';
            $this->components->twoColumnDetail($analysis->requested_at->format('Y-m-d H:i').' · '.$analysis->model_id, $analysis->status.($analysis->error_code ? ': '.$analysis->error_code : '').$took);
        }
        if ($recent->isEmpty()) {
            $this->components->twoColumnDetail('None yet', '');
        }

        if ($this->option('ping')) {
            $this->newLine();

            if (! $gemini->isConfigured()) {
                $this->components->error('Gemini isn’t set up: set '.implode(' and ', $gemini->missingSettings()).' in .env.');

                return self::FAILURE;
            }

            $this->components->info('Asking '.$gemini->modelId().' for a one-word reply, with text only and no photo');
            $ping = $gemini->ping(min(30, $timeout));

            $advice = match ($ping['code']) {
                null => "Answered in {$ping['seconds']} s.",
                'provider_timeout' => "No answer within {$ping['seconds']} s. This model isn’t answering this key right now, so photo analyses will time out. Try again later, or set GEMINI_MODEL to another current model from Google’s models page.",
                'provider_unreachable' => 'Couldn’t connect. Check this computer’s internet connection and GEMINI_API_URL.',
                'invalid_credentials' => 'The key was refused. Check GEMINI_API_KEY.',
                'model_not_found' => 'Google doesn’t know this model. Check GEMINI_MODEL.',
                'rate_limited' => 'Rate limited. Wait a minute and try again.',
                'provider_quota' => 'The account’s usage limit has been reached. Check the project’s quota or billing in Google AI Studio.',
                'invalid_request' => 'The request was refused. Check GEMINI_API_REVISION and GEMINI_THINKING_LEVEL.',
                'provider_unavailable' => 'Google reported a temporary error. Try again later.',
                default => 'Gemini answered, but not in the expected form.',
            };

            $ping['code'] === null ? $this->components->info($advice) : $this->components->error(($ping['http'] ? "HTTP {$ping['http']} · " : '').$ping['code'].'. '.$advice);

            return $ping['code'] === null ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
