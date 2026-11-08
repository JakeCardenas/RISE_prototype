<?php

namespace App\Providers;

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Services\Analysis\AnalysisProviders;
use App\Services\Analysis\CrackDetector;
use App\Services\Comparison\GeminiPhotoComparer;
use App\Services\Comparison\OpenAiCompatiblePhotoComparer;
use App\Services\Comparison\PhotoComparer;
use App\Services\Comparison\UnconfiguredPhotoComparer;
use App\Services\Gemini\GeminiClient;
use App\Support\HomeAreas;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The single-photo provider is replaceable; add providers in AnalysisProviders and choose one with RISE_ANALYSIS_PROVIDER
        $this->app->bind(CrackDetector::class, fn ($app) => $app->make(AnalysisProviders::class)->default());

        // The optional two-photo comparison stays unavailable until a provider is chosen and configured (see README.md)
        $this->app->bind(PhotoComparer::class, function () {
            $gemini = new GeminiPhotoComparer(new GeminiClient(config('services.gemini')));

            return match (config('services.photo_comparison.driver') ?: ($gemini->isConfigured() ? 'gemini' : null)) {
                'gemini' => $gemini,
                'openai_compatible' => new OpenAiCompatiblePhotoComparer(config('services.photo_comparison')),
                default => new UnconfiguredPhotoComparer,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login is limited per email address (normalised) from one IP, and per IP across all addresses; registration per IP.
        // Every limit returns the same message, whether or not an account exists for the address.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute(5)->by('email:'.$email.'|'.$request->ip())->response($this->tooManyAttempts('sign-in')),
                Limit::perMinute(20)->by('ip:'.$request->ip())->response($this->tooManyAttempts('sign-in')),
            ];
        });

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(3)->by('minute:'.$request->ip())->response($this->tooManyAttempts('sign-up')),
            Limit::perHour(20)->by('hour:'.$request->ip())->response($this->tooManyAttempts('sign-up')),
        ]);

        // Each AI request sends photos to an outside service, so a resident can only ask for a few at a time
        RateLimiter::for('ai-requests', fn (Request $request) => [
            Limit::perMinute(4)->by('minute:'.$request->user()->id)->response($this->slowDown('AI checks')),
            Limit::perHour(30)->by('hour:'.$request->user()->id)->response($this->slowDown('AI checks')),
        ]);

        RateLimiter::for('ai-feedback', fn (Request $request) => Limit::perMinute(20)->by($request->user()->id)->response($this->slowDown('feedback changes')));

        // The evaluation workspace: uploads, and runs that each send many research images to a provider
        RateLimiter::for('research', fn (Request $request) => Limit::perHour(120)->by($request->user()->id)->response($this->slowDown('changes')));
        RateLimiter::for('research-runs', fn (Request $request) => Limit::perHour(6)->by($request->user()->id)->response($this->slowDown('evaluation runs')));

        // How quickly an evaluation run's jobs call the provider; jobs over the limit wait in the queue
        RateLimiter::for('evaluation-provider', fn () => Limit::perMinute(max(1, (int) config('rise.evaluation_per_minute'))));

        // The layout's navigation lists the signed-in resident's own monitoring points by area, and marks the one being viewed
        View::composer('components.layout', function ($view) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $points = $user->monitoringPoints()->withCount('photoRecords')->orderBy('label')->get(['id', 'label', 'area']);
            $route = request()->route();
            $candidate = match (true) {
                $route?->parameter('point') instanceof MonitoringPoint => $route->parameter('point')->id,
                $route?->parameter('record') instanceof PhotoRecord => $route->parameter('record')->monitoring_point_id,
                default => request()->routeIs('records.create') ? request()->query('point') : null,
            };

            $view->with([
                'railPoints' => $points,
                // Grouped by room or area; a single unnamed group means the resident hasn't used areas yet
                'railGroups' => HomeAreas::group($points),
                'currentPointId' => $points->firstWhere('id', $candidate)?->id,
            ]);
        });
    }

    // Back to the form with one plain message and the typed name and email (never the password)
    private function tooManyAttempts(string $kind): Closure
    {
        return function (Request $request, array $headers) use ($kind) {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
            $wait = $seconds >= 90 ? ceil($seconds / 60).' minutes' : $seconds.' seconds';

            return back()
                ->withErrors(['email' => "Too many {$kind} attempts. Please wait {$wait} and try again."])
                ->withInput($request->only('name', 'email'));
        };
    }

    // Back to the page with a plain message; nothing was sent or saved
    private function slowDown(string $what): Closure
    {
        return function (Request $request, array $headers) use ($what) {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
            $wait = $seconds >= 90 ? ceil($seconds / 60).' minutes' : $seconds.' seconds';

            return back()->with('info', "That’s a lot of {$what} in a short time, so nothing was sent or saved. Please wait {$wait} and try again.");
        };
    }
}
