<?php

namespace App\Providers;

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Services\Analysis\CrackDetector;
use App\Services\Analysis\RoboflowCrackDetector;
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
        // The crack-image detector is replaceable; add providers here and choose one with RISE_ANALYSIS_PROVIDER
        $this->app->bind(CrackDetector::class, fn () => match (config('rise.analysis_provider')) {
            default => new RoboflowCrackDetector(config('services.roboflow')),
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
}
