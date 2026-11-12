<?php

namespace App\Services\Help;

// Gemini's reply to a typed question to Felix, after RISE's checks. An "app_help" or "general" reply carries Gemini's words;
// for a question about safety, RISE answers with its own fixed wording instead.
final class HelpReply
{
    public function __construct(
        public readonly string $topic,        // app_help, general, safety, or unclear when nothing showable was left
        public readonly ?string $answer,
        public readonly ?string $modelVersion,
    ) {}
}
