<?php

namespace App\Services\Analysis;

use Illuminate\Http\Client\ConnectionException;

// Tells apart a server that couldn't reach the provider from a provider that didn't answer in time. Only the error
// code leaves here: the exception text can include the endpoint, so it is read and never kept or logged.
final class ConnectionFailure
{
    public static function code(ConnectionException $e): string
    {
        $message = $e->getMessage();
        $connecting = preg_match('/Could not resolve|Resolving timed out|Failed to connect|Connection timed out|Connection refused|SSL connect|Network is unreachable/i', $message) === 1;

        return ! $connecting && preg_match('/timed out|Timeout was reached/i', $message) === 1 ? 'provider_timeout' : 'provider_unreachable';
    }
}
