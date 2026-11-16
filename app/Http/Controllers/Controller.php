<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Rows per page an admin table offers. */
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * The "Rows per page" choice, kept to the offered sizes so ?per_page=99999
     * cannot load the whole table in one go.
     */
    protected function perPage(Request $request, int $default, array $options = self::PER_PAGE): int
    {
        $asked = (int) $request->query('per_page');

        return in_array($asked, $options, true) ? $asked : $default;
    }
}
