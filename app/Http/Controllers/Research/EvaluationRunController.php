<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateItem;
use App\Models\EvaluationItem;
use App\Models\EvaluationPrediction;
use App\Models\EvaluationRun;
use App\Services\Analysis\AnalysisProviders;
use App\Support\EvaluationMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Evaluation runs: one provider and model over the labelled held-out images, scored against the human labels
class EvaluationRunController extends Controller
{
    // CREATE — queues every labelled held-out image, up to the configured limit, for the chosen provider
    public function store(Request $request, AnalysisProviders $providers)
    {
        $data = $request->validate(['provider' => ['required', Rule::in(array_keys($providers->configured()))]], [
            'provider.required' => 'Choose a provider that is set up.',
            'provider.in' => 'Choose a provider that is set up.',
        ]);
        $detector = $providers->make($data['provider']);

        $items = EvaluationItem::query()->where('split', 'held_out')->whereNotNull('label')
            ->orderBy('id')->limit(config('rise.evaluation_max_items'))->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['provider' => 'Label at least one held-out image first.']);
        }

        $run = DB::transaction(function () use ($request, $detector, $items) {
            $run = (new EvaluationRun)->forceFill([
                'provider' => $detector->name(),
                'model_id' => $detector->modelId(),
                'item_count' => $items->count(),
                'started_by' => $request->user()->id,
            ]);
            $run->save();

            foreach ($items as $item) {
                (new EvaluationPrediction)->forceFill([
                    'evaluation_run_id' => $run->id,
                    'evaluation_item_id' => $item->id,
                    // The label and conditions as they are now, so later relabelling never changes this run
                    'snapshot' => [
                        'label' => $item->label,
                        'regions' => $item->regions ?? [],
                        'blur' => $item->blur,
                        'lighting' => $item->lighting,
                        'surface' => $item->surface,
                    ],
                    'status' => EvaluationPrediction::PENDING,
                ])->save();
            }

            return $run;
        });

        foreach ($run->predictions as $prediction) {
            EvaluateItem::dispatch($prediction);
        }

        return redirect()->route('research.runs.show', $run)->with('success', 'Evaluation run started.');
    }

    // READ — progress, then the metrics, overall and by condition and model version
    public function show(EvaluationRun $run)
    {
        $predictions = $run->predictions()->get();
        $location = $run->measuresLocation();

        return view('research.run', [
            'run' => $run,
            'summary' => EvaluationMetrics::summarize($predictions, $location),
            'groups' => [
                'Focus' => ['blur', EvaluationItem::BLUR],
                'Lighting' => ['lighting', EvaluationItem::LIGHTING],
                'Surface' => ['surface', EvaluationItem::SURFACES],
                'Model version' => ['model_version', []],
            ],
            'grouped' => fn (string $by) => EvaluationMetrics::grouped($predictions, $by, $location),
            'failures' => $predictions->where('status', EvaluationPrediction::FAILED)->countBy('error_code'),
        ]);
    }

    // READ — a small status check, so the run page can reload once every image has a result
    public function status(EvaluationRun $run)
    {
        $waiting = $run->predictions()->whereIn('status', [EvaluationPrediction::PENDING, EvaluationPrediction::PROCESSING])->exists();

        return response()->json(['status' => $waiting ? 'processing' : 'completed']);
    }

    // READ — one row per image, identified only by its code: no files, names, accounts or authorization notes
    public function export(EvaluationRun $run)
    {
        $filename = 'rise-evaluation-'.$run->created_at->format('Y-m-d').'-'.$run->provider.'.csv';

        return response()->streamDownload(function () use ($run) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['item', 'human_label', 'human_boxes', 'blur', 'lighting', 'surface', 'provider', 'model_id', 'model_version', 'status', 'finding', 'predicted_boxes', 'best_iou', 'error_code']);

            foreach ($run->predictions()->with('item:id,code')->orderBy('evaluation_item_id')->lazy() as $prediction) {
                $human = $prediction->snapshot['regions'] ?? [];
                $predicted = $prediction->regions ?? [];
                $best = $run->measuresLocation() && $human && $predicted
                    ? round(max(array_map(fn ($box) => max(array_map(fn ($guess) => EvaluationMetrics::iou($box, $guess), $predicted)), $human)), 4)
                    : null;

                fputcsv($out, array_map($this->cell(...), [
                    $prediction->item?->code, $prediction->snapshot['label'] ?? null, count($human),
                    $prediction->snapshot['blur'] ?? null, $prediction->snapshot['lighting'] ?? null, $prediction->snapshot['surface'] ?? null,
                    $run->provider, $run->model_id, $prediction->model_version, $prediction->status, $prediction->finding,
                    count($predicted), $best, $prediction->error_code,
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    // A provider-reported value that starts like a formula is quoted, so a spreadsheet shows it as text
    private function cell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
