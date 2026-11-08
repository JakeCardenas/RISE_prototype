<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The evaluation workspace opens only for researcher accounts; anyone else gets a 404, so it can't be found by trying
class EnsureResearcher
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_researcher, 404);

        return $next($request);
    }
}
