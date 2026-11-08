@use('App\Models\AiFeedback')
@use('App\Models\EvaluationItem')
@use('App\Services\Analysis\AnalysisProviders')
@use('App\Support\EvaluationMetrics')

<x-layout title="Evaluation workspace">
    <x-page-header title="Evaluation workspace"
        description="For the capstone evaluation only. Research images and their labels are kept apart from residents’ spots and photos, and nothing here reads residents’ records." />

    @include('research.partials.nav')

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <section aria-labelledby="dataset-heading" class="sheet sheet-solid p-5 sm:p-7">
            <h2 id="dataset-heading" class="section-title">Research images</h2>
            <dl class="title-block mt-4">
                <div>
                    <dt>Images</dt>
                    <dd>{{ $counts['all'] }}</dd>
                </div>
                <div>
                    <dt>Not labelled yet</dt>
                    <dd>{{ $counts['unlabelled'] }}</dd>
                </div>
                @foreach (EvaluationItem::LABELS as $value => $text)
                    <div>
                        <dt>Held out · {{ $text }}</dt>
                        <dd>{{ $counts['heldOut'][$value] ?? 0 }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt>Development, never run</dt>
                    <dd>{{ $counts['development'] }}</dd>
                </div>
            </dl>
            <a href="{{ route('research.items.index') }}" class="btn btn-secondary mt-5"><x-icon name="upload" /> Add or label images</a>
        </section>

        <section aria-labelledby="start-heading" class="sheet sheet-solid p-5 sm:p-7">
            <h2 id="start-heading" class="section-title">Start an evaluation run</h2>
            @if (! $providers)
                <p class="mt-3">No analysis provider is set up on this server, so a run can’t start. The README explains how to set up Google Gemini or Roboflow.</p>
            @elseif ($eligible === 0)
                <p class="mt-3">Label at least one held-out image first. Only labelled held-out images are ever sent in a run.</p>
            @else
                <p class="mt-3">
                    Sends a re-encoded copy of each labelled held-out image to the provider you choose, with that provider’s fixed
                    instructions. Labels and conditions aren’t sent, and residents’ photos never are.
                </p>
                <form method="POST" action="{{ route('research.runs.store') }}" class="mt-5">
                    @csrf
                    <fieldset>
                        <legend class="field-label">Provider and model</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($providers as $name => $detector)
                                <label class="choice-chip">
                                    <input type="radio" name="provider" value="{{ $name }}" class="sr-only" @checked(old('provider', array_key_first($providers)) === $name)>
                                    {{ AnalysisProviders::label($name) }} · {{ $detector->modelId() }}
                                </label>
                            @endforeach
                        </div>
                        <x-field-error for="provider" />
                    </fieldset>
                    <button type="submit" class="btn btn-primary mt-5"><x-icon name="scan" /> Start run with {{ trans_choice('{1} 1 image|[2,*] :count images', $eligible) }}</button>
                </form>
                <p class="meta mt-3">
                    At most {{ config('rise.evaluation_max_items') }} images a run, and {{ config('rise.evaluation_per_minute') }} a minute so a run stays inside
                    provider rate limits. The queue worker must be running.
                </p>
            @endif
        </section>
    </div>

    <section aria-labelledby="runs-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="runs-heading" class="section-title">Runs</h2>
        @if ($runs->isEmpty())
            <p class="mt-3 text-muted">No runs yet, so there are no metrics. Metrics exist only once a run has scored labelled held-out images.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm tabular-nums">
                    <thead class="text-xs text-muted">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-semibold">Started</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Provider and model</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Images</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Progress</th>
                            <th scope="col" class="py-2 font-semibold"><span class="sr-only">Results</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            <tr class="border-t border-line">
                                <td class="py-2.5 pr-4"><x-local-time :time="$run->created_at" /></td>
                                <td class="py-2.5 pr-4">{{ $run->providerLabel() }} · {{ $run->model_id }}</td>
                                <td class="py-2.5 pr-4">{{ $run->item_count }}</td>
                                <td class="py-2.5 pr-4">
                                    {{ $run->waiting_count ? $run->waiting_count.' waiting' : 'Finished' }}{{ $run->failed_count ? ' · '.$run->failed_count.' failed' : '' }}
                                </td>
                                <td class="py-2.5"><a href="{{ route('research.runs.show', $run) }}" class="link">Results</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="ratings-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="ratings-heading" class="section-title">Residents’ ratings of live results</h2>
        <p class="mt-2 max-w-prose text-sm text-muted">
            Counts only: no resident’s note, photo or account is shown here. Ratings say how useful residents found results, not how
            accurate a model is, and nothing is trained on them.
        </p>
        @foreach ($feedback as $kind => $byModel)
            <h3 class="mt-5 text-sm font-semibold text-ink">{{ $kind }}</h3>
            @if ($byModel->isEmpty())
                <p class="mt-1 text-sm text-muted">No ratings yet.</p>
            @else
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[30rem] text-left text-sm tabular-nums">
                        <thead class="text-xs text-muted">
                            <tr>
                                <th scope="col" class="py-2 pr-4 font-semibold">Provider and model</th>
                                @foreach (AiFeedback::RATINGS as $text)
                                    <th scope="col" class="py-2 pr-4 font-semibold">{{ $text }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byModel as $model => $ratings)
                                <tr class="border-t border-line">
                                    <th scope="row" class="py-2.5 pr-4 font-medium">{{ $model }}</th>
                                    @foreach (array_keys(AiFeedback::RATINGS) as $rating)
                                        <td class="py-2.5 pr-4">{{ $ratings[$rating] ?? 0 }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    </section>

    <section aria-labelledby="method-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="method-heading" class="section-title">How runs are scored</h2>
        <ul class="mt-3 max-w-prose list-disc space-y-2 pl-5 marker:text-primary">
            <li>A human label of crack-like is the positive class; the finding “possible feature” is a positive prediction.</li>
            <li>Images a person labelled uncertain are left out and counted. A model answer of uncertain is an abstention, reported as coverage rather than counted as right or wrong.</li>
            <li>Precision and recall come with 95% Wilson intervals. Below {{ EvaluationMetrics::SMALL_SAMPLE }} decided images, every figure is marked as too few to rely on.</li>
            <li>Location is scored only for providers that return boxes (Roboflow): a drawn box counts as found when a predicted box overlaps it with an IoU of at least {{ EvaluationMetrics::IOU_THRESHOLD }}. Gemini isn’t asked for locations, so its runs have no location score.</li>
            <li>Labels are copied into a run when it starts. Development images are never run, so wording can be tried on them without touching the held-out set.</li>
            <li>Quote an accuracy figure only from a run like this, on held-out images labelled by people, and give its sample size.</li>
        </ul>
    </section>
</x-layout>
