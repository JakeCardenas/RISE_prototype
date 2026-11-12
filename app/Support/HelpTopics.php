<?php

namespace App\Support;

// What Fix-It Felix, RISE's helper, can answer by himself. The answers are written in resources/views/components/help-chat.blade.php
// and work without any AI. Greetings and questions about RISE itself are answered here straight away; a question asking
// RISE to judge a crack (safety, damage, causes, repairs) always gets RISE's own answer and is never sent to an AI
// provider; and when chat AI isn't set up, a typed question is matched to the closest answer here.
final class HelpTopics
{
    /** Each built-in answer's question, as offered on a suggestion chip */
    public const QUESTIONS = [
        'about' => 'What is RISE?',
        'start' => 'How do I start?',
        'helper' => 'What can you do?',
        'next' => 'What should I do next?',
        'follow-up' => 'How do I take a good follow-up?',
        'compare' => 'How do I compare photos?',
        'ai' => 'What can AI do?',
        'privacy' => 'Is my data private?',
        'safety' => 'Is my crack safe?',
    ];

    /** The suggestions Felix opens with */
    public const STARTERS = ['about', 'start', 'helper', 'next'];

    /** After each answer, up to three questions that naturally come next */
    public const FOLLOW_UPS = [
        'hello' => ['about', 'start', 'helper'],
        'thanks' => ['next', 'about', 'start'],
        'about' => ['start', 'privacy', 'helper'],
        'helper' => ['start', 'next', 'compare'],
        'start' => ['follow-up', 'next', 'compare'],
        'follow-up' => ['compare', 'next', 'ai'],
        'compare' => ['ai', 'follow-up', 'next'],
        'ai' => ['privacy', 'safety', 'compare'],
        'privacy' => ['ai', 'start', 'about'],
        'safety' => ['next', 'about', 'start'],
        'next' => ['follow-up', 'compare', 'helper'],
        'default' => ['about', 'start', 'next'],
    ];

    // Short messages that are only a greeting, a thank-you, or a question about RISE or Felix himself
    private const QUICK = [
        'hello' => '/^(?:hi|hello|hey|hiya|howdy|yo|hola|kumusta|musta|good (?:morning|afternoon|evening|day))(?: there)?(?: felix)?$/',
        'thanks' => '/^(?:(?:ok(?:ay)?|great|cool|nice)\s*)?(?:thanks?|thank you|thank u|ty|salamat)(?: (?:so much|a lot|felix))?$/',
        'about' => '/^(?:what(?: i|\')?s|what is|tell me about|explain)\s+(?:rise|this app|this|the app)(?: (?:for|about|app))?$|^what (?:is|does) (?:rise|it|this app) (?:for|do)$|^what(?:\'s| is) (?:rise|it|this) used for$/',
        'helper' => '/^(?:what can you (?:do|help with)|how can you help(?: me)?|who are you|what are you|can you help(?: me)?|help(?: me)?|what do you do)$/',
        'privacy' => '/^(?:is (?:my|it|rise|this) (?:data |photos? )?(?:private|safe to use|secure)|are my photos (?:private|safe|secure)|is my (?:data|information) (?:safe|private|secure)|who can see my (?:photos|spots|data|notes))$/',
    ];

    // Asking whether something is safe, serious, damaged or getting worse, why it happened, or how to repair it
    private const JUDGEMENT = '/\b(?:safe|safety|unsafe|danger\w*|hazard\w*|collaps\w*|structur\w*|foundation\w*|severe|severity|repair\w*|engineer\w*|subsid\w*|crumbl\w*|spreading|widen\w*|worsen\w*|how (?:bad|serious|dangerous|wide|deep)|is (?:it|this|that|my \w+) (?:bad|ok|okay|normal|serious|dangerous|fine|getting worse)|getting (?:worse|bigger|wider|longer)|fix (?:the|this|that|my|a|these|those) (?:crack|cracks|wall|walls|ceiling|floor|house|home))\b|\bcaus\w*\b.*\bcrack|\bcrack\w*\b.*\bcaus\w*|\bwhy (?:is|are|did|does|do) (?:\w+ ){0,3}crack/i';

    // Words that point to each built-in answer, for typed questions when chat AI isn't set up
    private const KEYWORDS = [
        'about' => '/\b(?:what is rise|about rise|rise for|this app|purpose|what does rise)\b/i',
        'helper' => '/\b(?:who are you|what can you|help me|can you help|felix)\b/i',
        'start' => '/\b(?:start\w*|begin\w*|new spot|creat\w*|add (?:a )?spot|track\w*|set ?up|first (?:photo|spot)|baseline|name)\b/i',
        'follow-up' => '/\b(?:follow[- ]?ups?|retake|again|same (?:place|angle|spot|distance)|light\w*|angle|distance|flash|blur\w*|focus|good photo|better photo|photo checks?)\b/i',
        'compare' => '/\b(?:compar\w*|side by side|slider|overlay|before and after|before\/after|difference\w*|two photos)\b/i',
        'ai' => '/\b(?:ai|a\.i\.?|gemini|artificial|analy[sz]\w*|detect\w*|model|machine)\b/i',
        'privacy' => '/\b(?:privat\w*|privacy|secur\w*|who can see|my data|personal)\b/i',
        'next' => '/\b(?:next|what (?:now|should i do|do i do)|where (?:do i|should i) (?:go|start)|stuck)\b/i',
    ];

    /** @return list<array{topic: string, question: string}> */
    public static function chips(array $topics): array
    {
        return array_map(fn (string $topic) => ['topic' => $topic, 'question' => self::QUESTIONS[$topic]], $topics);
    }

    public static function followUps(string $topic): array
    {
        return self::FOLLOW_UPS[$topic] ?? self::FOLLOW_UPS['default'];
    }

    // A short greeting, thank-you or question about RISE or Felix, answered without AI; null for anything else
    public static function quick(string $text): ?string
    {
        $plain = trim((string) preg_replace(['/[^\pL\pN\'\s]+/u', '/\s+/u'], [' ', ' '], mb_strtolower($text)));

        foreach (self::QUICK as $topic => $pattern) {
            if (preg_match($pattern, $plain) === 1) {
                return $topic;
            }
        }

        return null;
    }

    public static function asksForJudgement(string $text): bool
    {
        return preg_match(self::JUDGEMENT, $text) === 1;
    }

    // The built-in answer that fits a typed question best, or null when none clearly does
    public static function match(string $text): ?string
    {
        if ($topic = self::quick($text)) {
            return $topic;
        }

        if (self::asksForJudgement($text)) {
            return 'safety';
        }

        $scores = array_map(fn (string $pattern) => preg_match_all($pattern, $text), self::KEYWORDS);
        arsort($scores);
        [$best, $runnerUp] = array_pad(array_values($scores), 2, 0);

        return $best > 0 && $best > $runnerUp ? array_key_first($scores) : null;
    }
}
