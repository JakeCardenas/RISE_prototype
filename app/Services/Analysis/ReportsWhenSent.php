<?php

namespace App\Services\Analysis;

use Closure;
use Throwable;

// Notes the moment a provider request, photos included, has been fully sent, so the waiting page can tell "sending the
// photo" from "waiting for the result". Uses Guzzle's progress option; a failed note never stops the request.
trait ReportsWhenSent
{
    private function whenSent(?Closure $sent): array
    {
        if (! $sent) {
            return [];
        }

        $done = false;

        return ['progress' => function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use (&$done, $sent) {
            if ($done || $uploadTotal <= 0 || $uploaded < $uploadTotal) {
                return;
            }

            $done = true;

            try {
                $sent();
            } catch (Throwable) {
                // Only the progress display depends on this
            }
        }];
    }
}
