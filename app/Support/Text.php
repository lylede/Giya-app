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
}
