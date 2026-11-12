@use('App\Services\Help\HelpAssistant')
@use('App\Support\HelpTopics')
@props(['addPhotoUrl', 'currentPoint' => null])

@php
    $assistant = app(HelpAssistant::class);
    $provider = $assistant->isConfigured() ? $assistant->providerName() : null;
    // The resident's own picture of him, used exactly as provided
    $picture = asset('images/felix.png');
    // On a spot with two or more photos, "How do I compare photos?" can open that spot's comparison directly
    $compareHere = $currentPoint && $currentPoint->photo_records_count >= 2 ? $currentPoint : null;
    $follow = fn (string $topic) => implode(' ', HelpTopics::followUps($topic));
@endphp

{{--
    Fix-It Felix, RISE's helper (the resident's own pixel-art picture, used as provided): standing on a glass button in the
    bottom-right corner (above the tab bar on phones), and the chat he opens. The chat starts with a greeting and a few questions whose answers are written
    here, so they work without any AI; after each answer, Felix offers the questions that naturally come next. Typed
    questions go to HelpController. Nothing in the chat is saved. The button appears once help-chat.js has run.
--}}
<button type="button" class="helper-fab glass" aria-haspopup="dialog" aria-controls="helper-dialog" aria-expanded="false" data-help-open hidden>
    <span class="helper-sprite helper-fab-sprite" aria-hidden="true"><img src="{{ $picture }}" alt="" width="54" height="80" decoding="async"></span>
    <span class="helper-fab-label">Help</span>
</button>

<dialog id="helper-dialog" class="helper-dialog" aria-labelledby="helper-title" aria-describedby="helper-greeting"
    data-help-dialog data-ask-url="{{ route('help.ask') }}" data-next-url="{{ route('help.next-step') }}" data-provider="{{ $provider }}"
    data-questions='@json(HelpTopics::QUESTIONS)' data-default-follow="{{ $follow('default') }}">
    <div class="helper-window glass glass-shell">
        <header class="helper-header">
            <span class="helper-avatar" aria-hidden="true"><span class="helper-sprite"><img src="{{ $picture }}" alt="" width="54" height="80"></span></span>
            <div class="min-w-0 flex-1">
                <h2 id="helper-title" class="helper-title">Fix-It Felix</h2>
                <p class="helper-subtitle">Your RISE helper</p>
            </div>
            <button type="button" class="helper-close" data-help-close>
                <x-icon name="x" class="size-5" /><span class="sr-only">Close</span>
            </button>
        </header>

        <div class="helper-body sheet sheet-auth">
            <div class="helper-scroll" data-help-scroll>
                {{-- His stage: tap him to jump, tap the ground to walk there; with him focused, Space jumps and the arrow keys walk --}}
                <div class="helper-stage" data-help-stage>
                    <div class="helper-figure" data-help-figure>
                        <p class="helper-speech" aria-hidden="true">I can fix it!</p>
                        <button type="button" class="helper-actor" aria-describedby="helper-play-hint" data-help-actor>
                            <span class="helper-sprite" data-help-sprite><img src="{{ $picture }}" alt="" width="54" height="80"></span>
                            <span class="sr-only">Make Felix jump</span>
                        </button>
                    </div>
                    <span class="helper-ground" aria-hidden="true"></span>
                </div>
                <p id="helper-play-hint" class="sr-only">Press Space to make Felix jump, or the arrow keys to make him walk.</p>

                <ol class="helper-log" aria-label="Conversation" data-help-log>
                    <li class="helper-msg helper-msg-reply">
                        <p id="helper-greeting">Hi, I’m Felix! How can I help you today?</p>
                    </li>
                </ol>

                <div class="helper-chips" role="group" aria-label="Suggested questions" data-help-chips>
                    @foreach (HelpTopics::STARTERS as $topic)
                        <button type="button" class="helper-chip" style="--i: {{ $loop->index }}" data-help-question="{{ $topic }}">{{ HelpTopics::QUESTIONS[$topic] }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Where a typed question goes is said in the box itself, before anything is sent --}}
            <form class="helper-compose" novalidate data-help-form>
                @csrf
                <label for="helper-message" class="sr-only">Ask Felix a question</label>
                <input type="text" id="helper-message" name="message" maxlength="500" autocomplete="off" enterkeyhint="send" class="helper-input"
                    placeholder="{{ $provider ? 'Ask Felix · uses '.$provider : 'Ask Felix a question' }}">
                <button type="submit" class="helper-send" data-help-send>
                    <x-icon name="arrow-up" class="size-[1.125rem]" /><span class="sr-only">Send question</span>
                </button>
            </form>
            <p class="sr-only" role="status" data-help-status></p>
        </div>
    </div>

    {{-- Felix's built-in answers, each with the questions to offer after it --}}
    <template data-help-answer="hello" data-follow="{{ $follow('hello') }}">
        <p>Hi there! Ask me anything about RISE, or pick a question below.</p>
    </template>

    <template data-help-answer="thanks" data-follow="{{ $follow('thanks') }}">
        <p>You’re welcome! I’m here whenever you need me.</p>
    </template>

    <template data-help-answer="about" data-follow="{{ $follow('about') }}">
        <p>RISE is a private photo diary for cracks and marks around your home. Photograph the same spot now and then, and see how it looks over time.</p>
    </template>

    <template data-help-answer="helper" data-follow="{{ $follow('helper') }}">
        <p>I can show you how to start a spot, take good follow-up photos, compare them, and what to do next. Just ask!</p>
    </template>

    <template data-help-answer="start" data-follow="{{ $follow('start') }}">
        <p>Make a spot for one crack and give it a clear name. Its first photo becomes your baseline.</p>
        <div class="helper-actions">
            <a href="{{ route('points.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New spot</a>
        </div>
    </template>

    <template data-help-answer="follow-up" data-follow="{{ $follow('follow-up') }}">
        <p>Stand in the same place, at the same distance, in similar light. RISE shows your last photo so you can line them up.</p>
        <div class="helper-actions">
            <a href="{{ $addPhotoUrl }}" class="btn btn-primary"><x-icon name="camera" class="size-4" /> Add photo</a>
        </div>
    </template>

    <template data-help-answer="compare" data-follow="{{ $follow('compare') }}">
        <p>Open a spot and tap <strong>Compare before and after</strong>. Just remember: a different angle or light can look like a change.</p>
        <div class="helper-actions">
            @if ($compareHere)
                <a href="{{ route('points.compare', $compareHere) }}" class="btn btn-primary"><x-icon name="split" class="size-4" /> Compare this spot</a>
            @else
                <a href="{{ route('overview') }}#spots" class="btn btn-secondary"><x-icon name="list" class="size-4" /> Your spots</a>
            @endif
        </div>
    </template>

    <template data-help-answer="ai" data-follow="{{ $follow('ai') }}">
        <p>AI can describe a photo or point out differences between two. It can be wrong, and it can’t judge damage or safety. Nothing is sent unless you choose.</p>
    </template>

    <template data-help-answer="privacy" data-follow="{{ $follow('privacy') }}">
        <p>Only you can see your photos, notes and spots. A photo goes to AI only when you choose{{ $provider ? ', and questions you type to me go to '.$provider : '' }}.</p>
    </template>

    <template data-help-answer="safety" data-follow="{{ $follow('safety') }}">
        <p>I can’t tell if a crack is safe, and neither can RISE. If you’re worried, please contact a licensed civil or structural engineer.</p>
        <p>If there’s danger right now, leave the area and call for help.</p>
    </template>

    {{-- Used only if the resident's own next step can't be loaded --}}
    <template data-help-answer="next" data-follow="{{ $follow('next') }}">
        <p>Your Overview’s <strong>Next step</strong> card shows what to do next.</p>
        <div class="helper-actions">
            <a href="{{ route('overview') }}" class="btn btn-primary"><x-icon name="home" class="size-4" /> Go to Overview</a>
        </div>
    </template>

    <template data-help-answer="unclear" data-follow="{{ $follow('default') }}">
        <p>Hmm, I’m not sure about that one. Try asking another way, or pick a question below.</p>
    </template>

    <template data-help-answer="unavailable" data-follow="{{ $follow('default') }}">
        <p>I can’t answer that one yet: AI chat isn’t set up on this server, so I only know RISE’s basics. Nothing you typed was sent anywhere.</p>
    </template>
</dialog>
