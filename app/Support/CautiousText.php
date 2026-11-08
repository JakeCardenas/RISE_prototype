<?php

namespace App\Support;

// What RISE shows of a model's own words. Whatever a model says, RISE never shows measurements, causes, judgements of
// condition, severity, risk or safety, claims of growth or improvement, advice, or claimed certainty, and it shows
// "crack" only beside a hedge such as "may", "appears" or "crack-like". A sentence that breaks a rule, or is too long
// to show whole, is left out rather than shortened, so its meaning can't change.
final class CautiousText
{
    private const NEVER_SHOWN = '/\b\d+(?:[.,]\d+)?\s*(?:%|(?:mm|cm|m|millimet\w*|centimet\w*|met(?:er|re)s?|inch\w*|in|ft|feet|foot|percent)\b)|\b(?:safe\w*|unsafe|danger\w*|hazard\w*|risk\w*|sever\w*|serious\w*|critical\w*|urgent\w*|emergenc\w*|structur\w*|foundation\w*|collaps\w*|repair\w*|fix\w*|seal\w*|replac\w*|engineer\w*|inspect\w*|professional\w*|expert\w*|contractor\w*|settl\w*|subsid\w*|caus\w*|because|due to|unstable|stable|worse\w*|worsen\w*|deteriorat\w*|better|improv\w*|grow\w*|widen\w*|wider|longer|lengthen\w*|bigger|larger|enlarg\w*|spread\w*|expand\w*|progress\w*|good|bad|fine|okay|normal|harmless|minor|major|significant\w*|cosmetic|superficial|hairline|negligible|concern\w*|worr\w*|alarm\w*|should|must|recommend\w*|advis\w*|confiden\w*|certain\w*|definite\w*|probab\w*|likel\w*|chance\w*)\b/i';

    private const CRACK = '/\b(?:crack|fissur|fractur)/i';

    private const HEDGE = '/\b(?:appear\w*|may|might|could|possibl\w*|look\w*|seem\w*|resembl\w*|crack-like)\b/i';

    // One plain sentence, or null when it is empty, too long or says something RISE never shows
    public static function sentence(mixed $value, int $maxLength): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $value)) : '';

        if ($text === '' || mb_strlen($text) > $maxLength || preg_match(self::NEVER_SHOWN, $text)
            || (preg_match(self::CRACK, $text) && ! preg_match(self::HEDGE, $text))) {
            return null;
        }

        return $text;
    }

    /** @return list<string> Sentences that pass, in the model's order, up to the limit */
    public static function sentences(mixed $items, int $maxItems, int $maxLength): array
    {
        $clean = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (count($clean) === $maxItems) {
                break;
            }

            if (($text = self::sentence($item, $maxLength)) !== null) {
                $clean[] = $text;
            }
        }

        return $clean;
    }
}
