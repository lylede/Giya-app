<?php

namespace App\Support;

/** Small text clean-ups shared across GIYA. */
final class Text
{
    /**
     * Replace long dashes (em dash U+2014, en dash U+2013) with plain
     * punctuation. GIYA's house style uses none; the AI model likes them, so
     * its replies go through this. With [dash] standing for either one:
     *
     *   "9[dash]11 AM"                 becomes "9-11 AM"        (ranges keep a hyphen)
     *   "[dash] first item"            becomes "- first item"   (list markers)
     *   "Catmon Church [dash] Catmon"  becomes "Catmon Church, Catmon"
     */
    public static function withoutLongDashes(string $text): string
    {
        // Number ranges, e.g. 9[dash]11 or 1735[dash]1744
        $text = preg_replace('/(\d)\s*[\x{2013}\x{2014}]\s*(\d)/u', '$1-$2', $text);

        // A dash starting a line is a list marker.
        $text = preg_replace('/^(\s*)[\x{2013}\x{2014}]\s*/mu', '$1- ', $text);

        // Anywhere else it joins two parts of a sentence: a comma reads the same.
        $text = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $text);

        // Don't leave ",," or ", ." behind.
        return preg_replace(['/,\s*,/u', '/,\s*([.!?:;])/u'], [',', '$1'], $text);
    }

    /**
     * Take distances in kilometres out of an AI reply. GIYA only has
     * straight-line guesses, which are not what a devotee will travel, so
     * the chat does not give them. The prompt already asks the model not to;
     * this is the safety net for when it does anyway:
     *
     *   "Catmon Church (about 3 km)"       becomes "Catmon Church"
     *   "It is about 3 km away."           becomes "It is nearby."
     *   "about 3.2 km north of Sogod"      becomes "north of Sogod"
     *
     * Speeds such as "60 km/h" are left alone.
     */
    public static function withoutDistances(string $text): string
    {
        $qualifier = '(?:(?:about|around|roughly|approximately|almost|nearly|only|just|some|less than|more than|over|under|at least|up to)\s+|~\s*)';
        $number = '(?<![\d.,])\d+(?:[.,]\d+)?(?:\s*(?:-|to)\s*\d+(?:[.,]\d+)?)?';
        $unit = '(?:kms?|kilomet(?:er|re)s?|kilometros?)';
        $distance = '(?:'.$number.'\s*'.$unit.'|(?:an?|one|half an?)\s+kilomet(?:er|re))\b(?!\s*/)';

        $original = $text;

        // "(about 3 km)" or "(3 km north)": a bracket holding nothing but a distance.
        $text = preg_replace('/\s*\(\s*'.$qualifier.'?'.$distance.'(?:\s+[\p{L}-]+){0,3}\s*\)/iu', '', $text);

        // "within 5 km of Sogod" -> "near Sogod"
        $text = preg_replace('/\bwithin\s+'.$distance.'\s+(?:of|from)\b/iu', 'near', $text);

        // "3 km away" -> "nearby"
        $text = preg_replace('/(?<!\w)'.$qualifier.'?'.$distance.'\s+away\b/iu', 'nearby', $text);

        // Any other "3 km": drop it and let the sentence carry on.
        $text = preg_replace('/(?<!\w)'.$qualifier.'?'.$distance.'/iu', '', $text);

        if ($text === $original) {
            return $original;
        }

        // Tidy what the removals left behind (never a line's own indent).
        return preg_replace(['/(?<=\S)[ \t]{2,}/u', '/(?<=\S)[ \t]+([,.;:!?])/u'], [' ', '$1'], $text);
    }

    /**
     * A Giya AI reply as safe HTML for the chat bubble: escaped first, then
     * **bold**, _italic_ and line breaks. The page's JavaScript does the
     * same for new replies, so saved and live replies look identical.
     */
    public static function chatHtml(?string $text): string
    {
        $html = e((string) $text);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(^|[\s(])_([^_\n]+?)_(?=[\s.,!?)]|$)/mu', '$1<em>$2</em>', $html);

        return nl2br($html, false);
    }
}
