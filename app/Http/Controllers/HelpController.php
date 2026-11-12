<?php

namespace App\Http\Controllers;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Help\HelpAssistant;
use App\Support\FollowUps;
use App\Support\HelpTopics;
use App\Support\NextStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

// Fix-It Felix, RISE's helper, behind the floating button. His built-in answers live in the page; these two endpoints add the
// resident's own next step and answers to typed questions. Nothing here is saved: no question, answer or chat history.
class HelpController extends Controller
{
    // READ — "What should I do next?", from the resident's own spots and planned dates; nothing is sent to AI
    public function nextStep(Request $request): JsonResponse
    {
        $today = FollowUps::today($request);
        $points = NextStep::points($request->user());

        return response()->json([
            'html' => view('help.next-step', [
                'step' => NextStep::choose($points, NextStep::due($points, $today)),
                'today' => $today,
            ])->render(),
        ])->header('Cache-Control', 'no-store');
    }

    // A typed question. Greetings and questions about RISE or Felix get his own answer straight away. Questions asking
    // RISE to judge a crack get RISE's own answer and are never sent anywhere. Otherwise, with chat AI set up, only the
    // typed words (and this chat's earlier typed questions and AI answers) go to the AI provider; without it, the
    // closest built-in answer is used, and the reply says so.
    public function ask(Request $request, HelpAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array', 'max:6'],
            'history.*.role' => ['required', Rule::in(['resident', 'helper'])],
            'history.*.text' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => 'Type a question first.',
            'message.max' => 'Keep your question under 500 characters.',
        ]);

        $message = trim((string) preg_replace('/\s+/u', ' ', $data['message']));

        if ($topic = HelpTopics::quick($message)) {
            return $this->builtIn($topic);
        }

        if (HelpTopics::asksForJudgement($message)) {
            return $this->builtIn('safety');
        }

        if (! $assistant->isConfigured()) {
            $topic = HelpTopics::match($message);

            return $topic
                ? $this->builtIn($topic, 'AI chat isn’t set up on this server, so here’s my built-in answer. Nothing you typed was sent anywhere.')
                : response()->json(['kind' => 'unavailable']);
        }

        try {
            $reply = $assistant->reply($message, array_values($data['history'] ?? []));
        } catch (AnalysisFailed $e) {
            // Only the outcome is logged, never the question or anything the provider sent back
            Log::warning('RISE help chat failed.', array_filter(['error_code' => $e->errorCode, 'http_status' => $e->httpStatus]));

            return response()->json([
                'kind' => 'error',
                'message' => $this->failure($e->errorCode, $assistant->providerName()),
                'topic' => HelpTopics::match($message),
            ]);
        }

        return match ($reply->topic) {
            'safety' => $this->builtIn('safety'),
            'unclear' => $this->builtIn('unclear'),
            default => response()->json(['kind' => 'ai', 'answer' => $reply->answer, 'provider' => $assistant->providerName()]),
        };
    }

    private function builtIn(string $topic, ?string $note = null): JsonResponse
    {
        return response()->json(array_filter(['kind' => 'builtin', 'topic' => $topic, 'note' => $note]));
    }

    private function failure(string $code, string $provider): string
    {
        return match ($code) {
            'rate_limited' => "{$provider} is busy right now. Please try again in a minute.",
            'provider_timeout' => "{$provider} took too long to answer. Please try again.",
            'provider_unreachable', 'provider_unavailable' => "I couldn’t reach {$provider} just now. Please try again later.",
            'invalid_credentials', 'model_not_found', 'provider_quota', 'invalid_request' => "My AI isn’t working on this server right now. The person who runs RISE needs to check its {$provider} settings.",
            default => 'I couldn’t get a clear answer this time. Please try again.',
        };
    }
}
