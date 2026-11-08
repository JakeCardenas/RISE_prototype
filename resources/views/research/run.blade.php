@use('App\Models\EvaluationItem')
@use('App\Support\EvaluationMetrics')

@php
    $percent = fn (?float $value) => $value === null ? '—' : number_format($value * 100, 1).'%';
    $range = fn (?array $interval) => $interval ? number_format($interval[0] * 100, 1).'–'.number_format($interval[1] * 100, 1).'%' : null;
    $s = $summary;
@endphp

<x-layout title="Evaluation run">
    <x-page-header title="Evaluation run" :description="$run->providerLabel().' · '.$run->model_id" :back="route('research.index')" back-label="Runs and results">
        <x-slot:actions>
            <a href="{{ route('research.runs.export', $run) }}" class="btn btn-secondary"><x-icon name="upload" class="size-4 rotate-180" /> Download CSV</a>
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="progress-heading" class="sheet sheet-solid p-5 sm:p-7"
        @if ($s['waiting']) data-analysis-waiting data-status-url="{{ route('research.runs.status', $run) }}" @endif>
        <h2 id="progress-heading" class="section-title" @if ($s['waiting']) role="status" @endif>
            {{ $s['waiting'] ? 'Running: '.$s['waiting'].' of '.$s['total'].' images waiting' : 'Finished' }}
        </h2>
        <dl class="title-block mt-4 sm:grid-cols-4">
            <div>
                <dt>Started</dt>
                <dd><x-local-time :time="$run->created_at" /></dd>
            </div>
            <div>
                <dt>Images</dt>
                <dd>{{ $s['total'] }}</dd>
            </div>
            <div>
                <dt>With a result</dt>
                <dd>{{ $s['completed'] }}</dd>
            </div>
            <div>
                <dt>Failed, left out</dt>
                <dd>{{ $s['failed'] }}</dd>
            </div>
        </dl>
        @if ($failures->isNotEmpty())
            <p class="meta mt-3">Failures: {{ $failures->map(fn ($count, $code) => $code.' × '.$count)->implode(', ') }}</p>
        @endif
        @if ($s['waiting'])
            <p class="mt-3 text-sm text-muted">This page reloads when every image has a result. <a href="{{ request()->fullUrl() }}" class="link">Refresh now</a> to see progress.</p>
            <p class="mt-2 text-sm" data-still-waiting hidden>
                Still running. If nothing changes, the queue may not be running: start it with <code class="rounded bg-well px-1 py-0.5 text-[0.8125rem]">php artisan queue:work</code>.
            </p>
        @endif
    </section>

    <section aria-labelledby="metrics-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="metrics-heading" class="section-title">Detection against human labels</h2>

        @if ($s['completed'] === 0)
            <p class="mt-3 text-muted">No results yet, so there are no metrics.</p>
        @elseif ($s['decided'] === 0)
            <p class="mt-3">
                Metrics are unavailable: no image has both a human label of crack-like or not crack-like and a model answer other than
                uncertain.
            </p>
        @else
            @if ($s['small_sample'])
                <p class="mt-3 flex items-start gap-2.5 rounded-2xl border border-line bg-canvas px-4 py-3 text-sm" role="note">
                    <x-icon name="alert-triangle" class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>Only {{ $s['decided'] }} decided {{ Str::plural('image', $s['decided']) }}. Below {{ EvaluationMetrics::SMALL_SAMPLE }}, these figures are too few to rely on, and the intervals show how wide the uncertainty is.</span>
                </p>
            @endif

            <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Precision', $s['precision'], $range($s['precision_interval']), 'of '.($s['tp'] + $s['fp']).' predicted crack-like'],
                    ['Recall', $s['recall'], $range($s['recall_interval']), 'of '.($s['tp'] + $s['fn']).' labelled crack-like'],
                    ['F1', $s['f1'], null, 'from precision and recall'],
                    ['Coverage', $s['coverage'], null, $s['abstained'].' uncertain '.Str::plural('answer', $s['abstained']).' left out'],
                ] as [$name, $value, $interval, $basis])
                    <div class="rounded-2xl border border-line px-4 py-3">
                        <dt class="text-sm font-semibold text-muted">{{ $name }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tracking-[-0.02em] text-heading tabular-nums">{{ $percent($value) }}</dd>
                        <dd class="meta mt-1">{{ $interval ? '95% interval '.$interval.', ' : '' }}{{ $basis }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="meta mt-3">
                {{ $s['decided'] }} decided · {{ $s['human_uncertain'] }} labelled uncertain by a person, left out · {{ $s['failed'] }} failed, left out
            </p>
        @endif

        @if ($s['completed'] > 0)
            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[30rem] text-left text-sm tabular-nums">
                    <caption class="mb-2 text-left text-sm font-semibold text-ink">Human label (rows) by model finding (columns)</caption>
                    <thead class="text-xs text-muted">
                        <tr>
                            <td class="py-2 pr-4"></td>
                            @foreach (EvaluationMetrics::FINDINGS as $text)
                                <th scope="col" class="py-2 pr-4 font-semibold">{{ $text }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['matrix'] as $label => $row)
                            <tr class="border-t border-line">
                                <th scope="row" class="py-2.5 pr-4 font-medium">{{ EvaluationItem::LABELS[$label] }}</th>
                                @foreach ($row as $count)
                                    <td class="py-2.5 pr-4">{{ $count }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="location-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="location-heading" class="section-title">Location</h2>
        @if (! $s['location'])
            <p class="mt-3">Not applicable: {{ $run->providerLabel() }} isn’t asked where anything is, so this run has no location score.</p>
        @elseif ($s['location']['regions'] === 0)
            <p class="mt-3 text-muted">No boxes were drawn on the decided crack-like images, so location can’t be scored yet.</p>
        @else
            <dl class="title-block mt-4 sm:grid-cols-4">
                <div>
                    <dt>Drawn boxes found</dt>
                    <dd>{{ $percent($s['location']['region_recall']) }} <span class="font-medium text-muted">({{ $s['location']['matched'] }} of {{ $s['location']['regions'] }})</span></dd>
                </div>
                <div>
                    <dt>Predicted boxes matching one</dt>
                    <dd>{{ $percent($s['location']['box_precision']) }} <span class="font-medium text-muted">(of {{ $s['location']['boxes'] }})</span></dd>
                </div>
                <div class="col-span-2">
                    <dt>Mean best IoU per drawn box</dt>
                    <dd>{{ $s['location']['mean_best_iou'] === null ? '—' : number_format($s['location']['mean_best_iou'], 2) }}</dd>
                </div>
            </dl>
            <p class="meta mt-3">A drawn box counts as found when a predicted box overlaps it with an IoU of at least {{ EvaluationMetrics::IOU_THRESHOLD }}; each box is matched once.</p>
        @endif
    </section>

    <section aria-labelledby="groups-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="groups-heading" class="section-title">By condition and model version</h2>
        <p class="mt-2 max-w-prose text-sm text-muted">The same figures for each group of images. Small groups vary a lot by chance, so compare them with care.</p>

        @foreach ($groups as $title => [$by, $names])
            <h3 class="mt-6 text-sm font-semibold text-ink">{{ $title }}</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm tabular-nums">
                    <thead class="text-xs text-muted">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-semibold">{{ $title }}</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Decided</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Precision</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Recall</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">F1</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped($by) as $value => $group)
                            <tr class="border-t border-line">
                                <th scope="row" class="py-2.5 pr-4 font-medium">{{ $names[$value] ?? ($by === 'model_version' ? $value : Str::ucfirst($value)) }}</th>
                                <td class="py-2.5 pr-4">{{ $group['decided'] }}{{ $group['decided'] && $group['small_sample'] ? ' (few)' : '' }}</td>
                                <td class="py-2.5 pr-4">{{ $percent($group['precision']) }}</td>
                                <td class="py-2.5 pr-4">{{ $percent($group['recall']) }}</td>
                                <td class="py-2.5 pr-4">{{ $percent($group['f1']) }}</td>
                                <td class="py-2.5 pr-4">{{ $percent($group['coverage']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </section>
</x-layout>
