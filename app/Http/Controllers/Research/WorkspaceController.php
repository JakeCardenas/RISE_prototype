<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Models\AiFeedback;
use App\Models\EvaluationItem;
use App\Models\EvaluationPrediction;
use App\Models\EvaluationRun;
use App\Services\Analysis\AnalysisProviders;
use Illuminate\Support\Facades\DB;

// READ — the capstone evaluation workspace: the research dataset, runs, and residents' feedback as counts only
class WorkspaceController extends Controller
{
    public function __invoke(AnalysisProviders $providers)
    {
        $items = EvaluationItem::query();

        return view('research.index', [
            'counts' => [
                'all' => (clone $items)->count(),
                'unlabelled' => (clone $items)->whereNull('label')->count(),
                'development' => (clone $items)->where('split', 'development')->count(),
                'heldOut' => (clone $items)->where('split', 'held_out')->whereNotNull('label')
                    ->selectRaw('label, count(*) as total')->groupBy('label')->pluck('total', 'label'),
            ],
            'eligible' => min(EvaluationItem::query()->where('split', 'held_out')->whereNotNull('label')->count(), config('rise.evaluation_max_items')),
            'providers' => $providers->configured(),
            'runs' => EvaluationRun::query()->latest()->withCount([
                'predictions as waiting_count' => fn ($query) => $query->whereIn('status', [EvaluationPrediction::PENDING, EvaluationPrediction::PROCESSING]),
                'predictions as failed_count' => fn ($query) => $query->where('status', EvaluationPrediction::FAILED),
            ])->limit(50)->get(),
            // Ratings only: never a resident's note, photo or account
            'feedback' => [
                'Single-photo results' => $this->ratings('photo_analyses', 'photo_analysis_id', "coalesce(photo_analyses.model_version, photo_analyses.model_id, '')"),
                'Comparisons' => $this->ratings('photo_comparisons', 'photo_comparison_id', "coalesce(photo_comparisons.model_version, photo_comparisons.model_id, '')"),
            ],
        ]);
    }

    private function ratings(string $table, string $key, string $model)
    {
        return AiFeedback::query()
            ->join($table, "{$table}.id", '=', "ai_feedback.{$key}")
            ->select("{$table}.provider", DB::raw("{$model} as model"), 'ai_feedback.rating', DB::raw('count(*) as total'))
            ->groupBy("{$table}.provider", 'model', 'ai_feedback.rating')
            ->get()
            ->groupBy(fn ($row) => trim(AnalysisProviders::label($row->provider).' · '.$row->model, ' ·'))
            ->map(fn ($rows) => $rows->pluck('total', 'rating'));
    }
}
