<?php

namespace App\Services\Gemini;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Photos\ProcessedImage;
use Illuminate\Http\Client\ConnectionException;
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
        return filled($this->config['api_key'] ?? null)
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]{0,119}$#', (string) $this->modelId()) === 1
            && filter_var($this->config['api_url'] ?? null, FILTER_VALIDATE_URL) !== false;
    }

    public function maxImageSide(): int
    {
        return (int) ($this->config['max_image_side'] ?? 1536);
    }

    /**
     * @param  list<string|ProcessedImage>  $input  Text and photos, in the order Gemini reads them
     * @param  array<string, mixed>  $schema  The JSON schema the answer must follow
     *
     * @throws AnalysisFailed
     */
    public function answer(array $input, array $schema): GeminiAnswer
    {
        $started = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) $this->config['api_url'], '/'))
                ->timeout((int) ($this->config['timeout'] ?? 45))
                ->connectTimeout(10)
                ->acceptJson()
                ->withHeaders(array_filter([
                    'x-goog-api-key' => (string) $this->config['api_key'],
                    'Api-Revision' => $this->config['api_revision'] ?? null,
                ]))
                // Up to three attempts, only for rate limits, server errors and dropped connections, and only early on
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
            // The exception text can include the endpoint, so only a code is kept
            throw new AnalysisFailed($this->timedOut($e) ? 'provider_timeout' : 'provider_unreachable');
        }

        if ($response->failed()) {
            throw new AnalysisFailed($this->errorCodeFor($response));
        }

        $data = $response->json();
        $text = is_array($data) && ($data['status'] ?? null) === 'completed' ? $this->outputText($data) : null;
        $answer = $text !== null ? json_decode($this->jsonIn($text), true) : null;

        if (! is_array($answer) || array_is_list($answer)) {
            throw new AnalysisFailed('invalid_response');
        }

        return new GeminiAnswer(
            data: $answer,
            modelVersion: is_string($data['model'] ?? null) ? Str::limit($data['model'], 120, '') : $this->modelId(),
            requestId: is_string($data['id'] ?? null) ? Str::limit($data['id'], 120, '') : null,
        );
    }

    // A dropped connection is tried again; a request that ran out of time isn't, so the job stays inside its time limit
    private function isRetryable(Throwable $e): bool
    {
        return ($e instanceof ConnectionException && ! $this->timedOut($e))
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    private function timedOut(ConnectionException $e): bool
    {
        return Str::contains($e->getMessage(), ['timed out', 'Timeout'], true);
    }

    // Only the status and Google's error reason are read; the error message itself is never kept
    private function errorCodeFor(Response $response): string
    {
        $status = $response->status();
        $reasons = collect(data_get($response->json(), 'error.details'))->map(fn ($detail) => data_get($detail, 'reason'))->all();

        return match (true) {
            in_array('API_KEY_INVALID', $reasons, true), in_array($status, [401, 403], true) => 'invalid_credentials',
            $status === 404 => 'model_not_found',
            $status === 413 => 'image_too_large',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'provider_unavailable',
            default => 'invalid_request',
        };
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
