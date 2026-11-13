<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\Maintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * System maintenance: closing the site to devotees while the team works.
 */
class SystemController extends Controller
{
    public function index(): View
    {
        $backBy = SiteSetting::read('maintenance_back_by');

        return view('admin.system', [
            'on'      => Maintenance::on(),
            'text'    => SiteSetting::read('maintenance_message') ?? '',
            'backBy'  => $backBy ? Carbon::parse($backBy)->format('Y-m-d\TH:i') : '',
            'since'   => ($since = SiteSetting::read('maintenance_since')) ? Carbon::parse($since) : null,
            'by'      => SiteSetting::read('maintenance_by'),
            'ready'   => Schema::hasTable('site_settings'),
        ]);
    }

    /** What a devotee sees while the site is closed, without closing it. */
    public function preview(): View
    {
        return view('maintenance', [
            'message' => Maintenance::message(),
            'backBy'  => Maintenance::backBy(),
            'preview' => true,
        ]);
    }

    public function maintenance(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('site_settings')) {
            return back()->with('error', 'The settings table is missing. Run "php artisan migrate" first.');
        }

        $data = $request->validate([
            'state'   => ['required', 'in:on,off'],
            'message' => ['nullable', 'string', 'max:300'],
            'back_by' => ['nullable', 'date'],
        ], [
            'message.max' => 'Keep the message to 300 characters.',
            'back_by.date' => 'Pick a date and time for "Back by", or leave it empty.',
        ]);

        $turningOn = $data['state'] === 'on' && ! Maintenance::on();

        $values = [
            'maintenance'         => $data['state'],
            'maintenance_message' => trim((string) ($data['message'] ?? '')) ?: null,
            'maintenance_back_by' => filled($data['back_by'] ?? null) ? Carbon::parse($data['back_by'])->toIso8601String() : null,
        ];

        if ($turningOn) {
            $values['maintenance_since'] = now()->toIso8601String();
            $values['maintenance_by'] = $request->user()->name;
        }

        SiteSetting::write($values);

        return back()->with('success', match (true) {
            $turningOn              => 'Maintenance mode is on. Devotees now see the closed page.',
            $data['state'] === 'on' => 'Maintenance message updated.',
            default                 => 'Maintenance mode is off. GIYA is open again.',
        });
    }
}
