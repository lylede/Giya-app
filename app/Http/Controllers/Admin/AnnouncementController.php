<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * System announcements: a message from the team to every pilgrim.
 */
class AnnouncementController extends Controller
{
    /** How long the banner stays up, in days. 0 means until an admin stops it. */
    public const SHOW_FOR = [1 => '1 day', 3 => '3 days', 7 => '1 week', 0 => 'Until I stop it'];

    public function index(): View
    {
        $ready = Schema::hasTable('announcements');

        return view('admin.announcements', [
            'ready'         => $ready,
            'announcements' => $ready ? Announcement::with('author')->latest('id')->paginate(10) : collect(),
            'current'       => $ready ? Announcement::current() : null,
            'showFor'       => self::SHOW_FOR,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('announcements')) {
            return back()->with('error', 'The announcements table is missing. Run "php artisan migrate" first.');
        }

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:120'],
            'message'  => ['required', 'string', 'max:300'],
            'show_for' => ['required', 'integer', 'in:'.implode(',', array_keys(self::SHOW_FOR))],
            'notify'   => ['nullable', 'boolean'],
        ], [
            'title.required'   => 'Give the announcement a short title.',
            'message.required' => 'Write the announcement.',
            'message.max'      => 'Keep the announcement to 300 characters.',
        ]);

        $days = (int) $data['show_for'];

        $announcement = Announcement::create([
            'title'      => $data['title'],
            'message'    => $data['message'],
            'created_by' => $request->user()->id,
            'is_active'  => true,
            'show_until' => $days > 0 ? now()->addDays($days) : null,
        ]);

        $told = 0;

        if ($request->boolean('notify')) {
            $told = Notifier::announcement($announcement);
            $announcement->update(['sent_to' => $told]);
        }

        return back()->with('success', $told > 0
            ? "Announcement posted and sent to {$told} ".($told === 1 ? 'person' : 'people').'.'
            : 'Announcement posted on the banner.');
    }

    /** Take it off the banner. Notifications already sent stay where they are. */
    public function stop(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['is_active' => false]);

        return back()->with('success', 'The announcement is no longer on the banner.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
