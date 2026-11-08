<?php

namespace App\Services\Comparison;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Photos\ProcessedImage;
use App\Support\CautiousText;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

// A vision model behind an OpenAI-compatible chat completions endpoint: a hosted service, or a model running on the
// RISE server itself. It receives two resized JPEG copies with every embedded detail removed, plus a fixed instruction;
// never notes, spot names or account details. See README.md for setup.
class OpenAiCompatiblePhotoComparer implements PhotoComparer
{
    private const MAX_ITEMS = 3;

    private const MAX_LENGTH = 200;

    // What the model is asked for, and what it must never say
    private const INSTRUCTIONS = <<<'TEXT'
        You compare two photos of the same spot on a wall, ceiling or floor in a home. Photo A is earlier; photo B is later.
        Describe only what you can see, in plain, cautious words such as "appears" or "may".
        Reply with JSON only, shaped like {"visible_differences": [], "photo_differences": []}.
        visible_differences: up to 3 short sentences about differences in what is visible on the surface between A and B, such as a line or mark seen in one photo but not the other, or one that looks darker or more visible. Use an empty list if you can't see a clear difference.
        photo_differences: up to 3 short sentences about differences in how the photos were taken: camera angle, distance, framing, lighting, shadows or focus.
        Never say that anything grew, widened, lengthened, spread, worsened or improved. Never estimate a size or measurement. Never name a cause. Never judge severity, risk or urgency. Never suggest repairs or inspections. Never say whether the home is safe or unsafe. Keep each sentence under 25 words.
        TEXT;

    /** @param  array<string, mixed>  $config  config('services.photo_comparison') */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return Str::limit(trim((string) ($this->config['provider_name'] ?? '')), 60, '');
    }

    public function modelId(): ?string
    {
        return filled($this->config['model'] ?? null) ? trim($this->config['model']) : null;
    }

    // Needs the name residents will see, an endpoint and a model; a key only if the endpoint asks for one
    public function isConfigured(): bool
    {
        return filled($this->name())
            && filled($this->modelId())
            && filter_var($this->config['api_url'] ?? null, FILTER_VALIDATE_URL) !== false;
    }

    public function maxImageSide(): int
    {
        return (int) ($this->config['max_image_side'] ?? 1024);
    }

    public function compare(ProcessedImage $earlier, ProcessedImage $later): ComparisonResult
    {
        $image = fn (ProcessedImage $photo) => ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.base64_encode($photo->bytes)]];

        try {
            $response = Http::baseUrl(rtrim((string) $this->config['api_url'], '/'))
                ->timeout((int) ($this->config['timeout'] ?? 45))
                ->connectTimeout(10)
                ->acceptJson()
                ->when(filled($this->config['api_key'] ?? null), fn ($request) => $request->withToken($this->config['api_key']))
                // Up to three attempts, only for rate limits, server errors and dropped connections
                ->retry([1000, 3000], when: fn (Throwable $e) => $this->isRetryable($e), throw: false)
                ->post('/chat/completions', [
                    'model' => $this->modelId(),
                    'temperature' => 0.2,
                    'max_tokens' => 400,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCTIONS],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => 'Photo A (earlier), then photo B (later).'],
                            $image($earlier),
                            $image($later),
                        ]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // The exception text can include the endpoint and key, so only a code is kept
            throw new AnalysisFailed(Str::contains($e->getMessage(), ['timed out', 'Timeout'], true) ? 'provider_timeout' : 'provider_unreachable');
        }

        if ($response->failed()) {
            throw new AnalysisFailed($this->errorCodeFor($response->status()));
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($this->jsonIn($content), true) : null;

        if (! is_array($parsed)) {
            throw new AnalysisFailed('invalid_response');
        }

        $model = data_get($response->json(), 'model');

        return new ComparisonResult(
            visible: CautiousText::sentences($parsed['visible_differences'] ?? [], self::MAX_ITEMS, self::MAX_LENGTH),
            photo: CautiousText::sentences($parsed['photo_differences'] ?? [], self::MAX_ITEMS, self::MAX_LENGTH),
            modelVersion: is_string($model) ? Str::limit($model, 120, '') : $this->modelId(),
        );
    }

    private function isRetryable(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    private function errorCodeFor(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'invalid_credentials',
            in_array($status, [402, 423], true) => 'provider_quota',
            $status === 404 => 'model_not_found',
            $status === 413 => 'image_too_large',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'provider_unavailable',
            default => 'unsupported_image',
        };
    }

    // Some models wrap their JSON in a sentence or a code fence; only the outermost object is read
    private function jsonIn(string $content): string
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        return $start !== false && $end > $start ? substr($content, $start, $end - $start + 1) : $content;
    }
}
