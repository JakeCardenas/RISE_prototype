<?php

namespace App\Services\Analysis;

use App\Services\Photos\ProcessedImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

// Roboflow Serverless API: POST {api_url}/{project}/{version} with a base64 image body.
// See README.md for setup and the documented request and response shape.
class RoboflowCrackDetector implements CrackDetector
{
    private const MAX_REGIONS = 100;

    private const MAX_POINTS = 400;

    /** @param  array<string, mixed>  $config  config('services.roboflow') */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'roboflow';
    }

    public function modelId(): ?string
    {
        return filled($this->config['model_id'] ?? null) ? trim($this->config['model_id']) : null;
    }

    public function confidenceThreshold(): ?float
    {
        return (float) ($this->config['confidence'] ?? 0.4);
    }

    // Needs a key and a model ID shaped like "project/version"
    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null)
            && preg_match('#^[A-Za-z0-9._-]+/\d+$#', (string) $this->modelId()) === 1;
    }

    public function maxImageSide(): int
    {
        return (int) ($this->config['max_image_side'] ?? 1280);
    }

    public function detect(ProcessedImage $image): DetectionResult
    {
        try {
            $response = Http::baseUrl(rtrim((string) $this->config['api_url'], '/'))
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->connectTimeout(10)
                ->acceptJson()
                ->withQueryParameters([
                    'api_key' => $this->config['api_key'],
                    'confidence' => $this->confidenceThreshold(),
                    'response_mask_format' => 'polygon',
                    // Ask Roboflow not to add resident photos to a training dataset
                    'disable_active_learning' => 'true',
                ])
                ->withBody(base64_encode($image->bytes), 'application/x-www-form-urlencoded')
                // Up to three attempts, only for rate limits, server errors and dropped connections
                ->retry([1000, 3000], when: fn (Throwable $e) => $this->isRetryable($e), throw: false)
                ->post('/'.$this->modelId());
        } catch (ConnectionException $e) {
            // The exception text includes the request URL and key, so only a code is kept
            throw new AnalysisFailed(Str::contains($e->getMessage(), ['timed out', 'Timeout'], true) ? 'provider_timeout' : 'provider_unreachable');
        }

        if ($response->failed()) {
            throw new AnalysisFailed($this->errorCodeFor($response->status()));
        }

        $data = $response->json();

        if (! is_array($data) || ! is_array($data['predictions'] ?? null)) {
            throw new AnalysisFailed('invalid_response');
        }

        return $this->normalize($data, $image);
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

    // Keeps only validated numbers and short labels, scaled to fractions of the analysed image
    private function normalize(array $data, ProcessedImage $sent): DetectionResult
    {
        $image = is_array($data['image'] ?? null) ? $data['image'] : [];
        $image = array_is_list($image) ? ($image[0] ?? []) : $image;
        $width = $this->positive($image['width'] ?? null) ?? $sent->width;
        $height = $this->positive($image['height'] ?? null) ?? $sent->height;

        // Locations are only kept when they refer to the copy RISE sent: the same shape, give or take rounding
        if (abs($width / $height - $sent->width / $sent->height) > 0.02 * $sent->width / $sent->height) {
            throw new AnalysisFailed('invalid_response');
        }

        // Some responses give outline points on a smaller mask grid
        $grid = is_array($data['mask_metadata'] ?? null) ? $data['mask_metadata'] : [];
        $onGrid = ($grid['coordinate_system'] ?? null) === 'mask_grid';
        $scaleX = $onGrid ? ($this->positive($grid['scale_x'] ?? null) ?? 1.0) : 1.0;
        $scaleY = $onGrid ? ($this->positive($grid['scale_y'] ?? null) ?? 1.0) : 1.0;

        $regions = [];

        foreach (array_slice($data['predictions'], 0, self::MAX_REGIONS) as $prediction) {
            if (! is_array($prediction) || ! $this->allNumeric($prediction, ['x', 'y', 'width', 'height', 'confidence'])) {
                continue;
            }

            // Roboflow boxes are centre-based; each edge is clipped to the photo, and a box with no area is left out
            $left = $this->fraction(($prediction['x'] - $prediction['width'] / 2) / $width);
            $top = $this->fraction(($prediction['y'] - $prediction['height'] / 2) / $height);
            $box = [$left, $top, round($this->fraction(($prediction['x'] + $prediction['width'] / 2) / $width) - $left, 5), round($this->fraction(($prediction['y'] + $prediction['height'] / 2) / $height) - $top, 5)];

            if ($box[2] <= 0 || $box[3] <= 0) {
                continue;
            }

            $polygon = [];

            foreach (is_array($prediction['points'] ?? null) ? $prediction['points'] : [] as $point) {
                if (is_array($point) && is_numeric($point['x'] ?? null) && is_numeric($point['y'] ?? null)) {
                    $polygon[] = [$this->fraction($point['x'] * $scaleX / $width), $this->fraction($point['y'] * $scaleY / $height)];
                }
            }

            $regions[] = [
                'label' => Str::limit(preg_replace('/[^\pL\pN _-]/u', '', (string) ($prediction['class'] ?? '')), 40, ''),
                'score' => round(min(1, max(0, (float) $prediction['confidence'])), 3),
                'box' => $box,
                // Only the model's own outline is drawn; without one, the box is shown instead
                'polygon' => count($polygon) >= 3 ? $this->thin($polygon) : null,
            ];
        }

        usort($regions, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $model = $data['resolved_model']['model_id'] ?? null;
        $requestId = $data['inference_id'] ?? null;

        return new DetectionResult(
            image: ['width' => (int) round($width), 'height' => (int) round($height)],
            regions: $regions,
            modelVersion: is_string($model) ? Str::limit($model, 120, '') : $this->modelId(),
            requestId: is_string($requestId) ? Str::limit($requestId, 120, '') : null,
        );
    }

    private function allNumeric(array $values, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! is_numeric($values[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }

    private function fraction(float $value): float
    {
        return round(min(1, max(0, $value)), 5);
    }

    // Very long outlines are thinned to keep stored results small
    private function thin(array $polygon): array
    {
        $step = (int) ceil(count($polygon) / self::MAX_POINTS);

        return $step > 1 ? array_values(array_filter($polygon, fn ($point, $index) => $index % $step === 0, ARRAY_FILTER_USE_BOTH)) : $polygon;
    }
}
