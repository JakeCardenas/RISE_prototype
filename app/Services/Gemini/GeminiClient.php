<?php

namespace App\Services\Gemini;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\ConnectionFailure;
use App\Services\Analysis\ReportsWhenSent;
use App\Services\Photos\ProcessedImage;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

// Google's Gemini Interactions API: POST {api_url}/interactions with RISE's fixed instructions and inline JPEG copies,
// asking for JSON that follows a schema. Requests go from the RISE server only, with store: false because RISE never
// continues an interaction. See README.md for setup and the documented request and response shape.
class GeminiClient
{
    use ReportsWhenSent;

    // Retries stop once this much time has passed, so a slow first attempt is never followed by two more
    private const RETRY_WINDOW_SECONDS = 15;

    /** @param  array<string, mixed>  $config  config('services.gemini') */
    public function __construct(private readonly array $config) {}

    public function modelId(): ?string
    {
        return filled($this->config['model'] ?? null) ? trim($this->config['model']) : null;
    }

    // Needs a key, a model ID and an endpoint
    public function isConfigured(): bool
    {
        return $this->missingSettings() === [];
    }

    /** @return list<string> */
    public function missingSettings(): array
    {
        return array_keys(array_filter([
            'GEMINI_API_KEY' => blank($this->config['api_key'] ?? null),
            'GEMINI_MODEL' => preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]{0,119}$#', (string) $this->modelId()) !== 1,
            'GEMINI_API_URL' => filter_var($this->config['api_url'] ?? null, FILTER_VALIDATE_URL) === false,
        ]));
    }

    public function maxImageSide(): int
    {
        return (int) ($this->config['max_image_side'] ?? 1536);
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 45);
    }

    /**
     * @param  list<string|ProcessedImage>  $input  Text and photos, in the order Gemini reads them
     * @param  array<string, mixed>  $schema  The JSON schema the answer must follow
     * @param  Closure|null  $sent  Called once the whole request, photos included, has been sent
     *
     * @throws AnalysisFailed
     */
    public function answer(array $input, array $schema, ?Closure $sent = null): GeminiAnswer
    {
        $started = microtime(true);

        try {
            $response = $this->http($this->timeout())
                ->withOptions($this->whenSent($sent))
                // Up to three attempts, only for per-minute rate limits, temporary server errors and failed connections,
                // and only early on; a request that ran out of time isn't repeated
                ->retry([1000, 3000], when: fn (Throwable $e) => $this->isRetryable($e) && microtime(true) - $started < self::RETRY_WINDOW_SECONDS, throw: false)
                ->post('/interactions', array_filter([
                    'model' => $this->modelId(),
                    'input' => array_map(fn (string|ProcessedImage $part) => $part instanceof ProcessedImage
                        ? ['type' => 'image', 'data' => base64_encode($part->bytes), 'mime_type' => 'image/jpeg']
                        : ['type' => 'text', 'text' => $part], $input),
                    'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
                    'generation_config' => filled($this->config['thinking_level'] ?? null) ? ['thinking_level' => $this->config['thinking_level']] : null,
                    'store' => false,
                ], fn ($value) => $value !== null));
        } catch (ConnectionException $e) {
            throw new AnalysisFailed(ConnectionFailure::code($e));
        }

        if ($response->failed()) {
            throw new AnalysisFailed($this->errorCodeFor($response), $response->status());
        }

        $data = $response->json();
        $text = is_array($data) && ($data['status'] ?? null) === 'completed' ? $this->outputText($data) : null;
        $answer = $text !== null ? json_decode($this->jsonIn($text), true) : null;

        if (! is_array($answer) || array_is_list($answer)) {
            throw new AnalysisFailed('invalid_response', $response->status());
        }

        return new GeminiAnswer(
            data: $answer,
            modelVersion: is_string($data['model'] ?? null) ? Str::limit($data['model'], 120, '') : $this->modelId(),
            requestId: is_string($data['id'] ?? null) ? Str::limit($data['id'], 120, '') : null,
        );
    }

    /**
     * A one-line text request with no photo, to check that the configured model answers at all
     *
     * @return array{seconds: float, http: int|null, code: string|null} code is null when it answered
     */
    public function ping(int $timeout = 20): array
    {
        $started = microtime(true);

        try {
            $response = $this->http($timeout)->post('/interactions', [
                'model' => $this->modelId(),
                'input' => [['type' => 'text', 'text' => 'Reply with the single word ok.']],
                'store' => false,
            ]);
        } catch (ConnectionException $e) {
            return ['seconds' => round(microtime(true) - $started, 1), 'http' => null, 'code' => ConnectionFailure::code($e)];
        }

        $answered = $response->successful() && ($response->json('status') === 'completed');

        return [
            'seconds' => round(microtime(true) - $started, 1),
            'http' => $response->status(),
            'code' => $answered ? null : ($response->failed() ? $this->errorCodeFor($response) : 'invalid_response'),
        ];
    }

    // The key travels in a header, never in the URL, so it can't end up in logs or error messages
    private function http(int $timeout): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config['api_url'], '/'))
            ->timeout($timeout)
            ->connectTimeout(10)
            ->acceptJson()
            ->withHeaders(array_filter([
                'x-goog-api-key' => (string) $this->config['api_key'],
                'Api-Revision' => $this->config['api_revision'] ?? null,
            ]));
    }

    private function isRetryable(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return ConnectionFailure::code($e) === 'provider_unreachable';
        }

        return $e instanceof RequestException
            && in_array($this->errorCodeFor($e->response), ['rate_limited', 'provider_unavailable'], true);
    }

    // Only the status and Google's structured error fields are read; the message is read only to spot a problem with
    // the photo, and is never kept or logged
    private function errorCodeFor(Response $response): string
    {
        $status = $response->status();
        $json = $response->json();
        // The Interactions API sends {"error": {...}}; other Google API errors, such as a rejected key, come as [{"error": {...}}]
        $error = data_get($json, 'error') ?? data_get($json, '0.error');
        $error = is_array($error) ? $error : [];
        $code = is_string($error['code'] ?? null) ? strtolower($error['code']) : null;
        $state = is_string($error['status'] ?? null) ? $error['status'] : null;
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];
        $reasons = array_filter(array_map(fn ($detail) => data_get($detail, 'reason'), $details));
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';

        return match (true) {
            in_array('API_KEY_INVALID', $reasons, true), in_array($status, [401, 403], true), in_array($state, ['UNAUTHENTICATED', 'PERMISSION_DENIED'], true) => 'invalid_credentials',
            $status === 404, $code === 'not_found', $state === 'NOT_FOUND' => 'model_not_found',
            $status === 413 => 'image_too_large',
            $status === 429 => $this->dailyLimit($details) ? 'provider_quota' : 'rate_limited',
            $status === 504, $state === 'DEADLINE_EXCEEDED' => 'provider_timeout',
            $status >= 500 => 'provider_unavailable',
            preg_match('/\b(image|mime|inline data|base64)\b/i', $message) === 1 => 'unsupported_image',
            default => 'invalid_request',
        };
    }

    // A 429 for a daily quota won't clear by trying again soon; a per-minute rate limit will
    private function dailyLimit(array $details): bool
    {
        foreach ($details as $detail) {
            foreach ((array) data_get($detail, 'violations', []) as $violation) {
                if (str_contains((string) data_get($violation, 'quotaId'), 'PerDay')) {
                    return true;
                }
            }
        }

        return false;
    }

    // The text of the model's last output step; thought summaries and other step types are skipped
    private function outputText(array $data): ?string
    {
        $text = null;

        foreach (is_array($data['steps'] ?? null) ? $data['steps'] : [] as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output' || ! is_array($step['content'] ?? null)) {
                continue;
            }

            $parts = array_filter($step['content'], fn ($part) => is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null));
            $text = implode('', array_column($parts, 'text')) ?: $text;
        }

        return $text;
    }

    // Structured output is plain JSON, but a code fence or a sentence around it is tolerated; only the object is read
    private function jsonIn(string $content): string
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        return $start !== false && $end > $start ? substr($content, $start, $end - $start + 1) : $content;
    }
}
