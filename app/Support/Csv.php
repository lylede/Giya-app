<?php

namespace App\Support;

/**
 * Write CSV rows that are safe to open in Excel or Google Sheets.
 *
 * A cell starting with = + - @ (or a tab / carriage return) is run as a
 * formula by spreadsheet apps. Names, emails, event titles and review text in
 * GIYA's exports are typed by devotees, so a devotee named
 * =HYPERLINK("http://evil.example","Click") would become a live link - or
 * worse - on the admin's machine. Such cells are prefixed with a single quote
 * so they display as text. (OWASP: "CSV Injection".)
 *
 * Real numbers are left alone, so -12 and -3.5 still sum in a spreadsheet.
 */
final class Csv
{
    /** @param  resource  $handle */
    public static function put($handle, array $fields): int|false
    {
        return fputcsv($handle, array_map([self::class, 'cell'], $fields));
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
