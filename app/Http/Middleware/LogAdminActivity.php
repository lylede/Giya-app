<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes the activity log: what each admin changed in the admin panel.
 *
 * Done here, around every admin route, rather than inside each controller,
 * so an action added to the panel later is logged without anyone having to
 * remember to. Looking at a page is not logged; changing something is, and
 * so is downloading a list of people or a report.
 */
class LogAdminActivity
{
    /** What each action is called in the log. Anything not listed is still logged, by its route name. */
    private const ACTIONS = [
        'admin.users.update'          => 'Edited a user',
        'admin.users.confirm'         => "Confirmed a user's email",
        'admin.users.destroy'         => 'Deleted a user',
        'admin.destinations.import'   => 'Imported destinations',
        'admin.destinations.toggle'   => 'Showed or hid a destination',
        'admin.destinations.photo'    => "Changed a destination's photo",
        'admin.destinations.destroy'  => 'Deleted a destination',
        'admin.schedules.destroy'     => 'Deleted a schedule',
        'admin.feedback.update'       => 'Reviewed feedback',
        'admin.announcements.store'   => 'Posted an announcement',
        'admin.announcements.stop'    => 'Stopped an announcement',
        'admin.announcements.destroy' => 'Deleted an announcement',
    ];

    /** Pages that only read, but hand over a file of people's data. */
    private const DOWNLOADS = [
        'admin.users.export'     => 'Downloaded the users list',
        'admin.schedules.export' => 'Downloaded the schedules list',
        'admin.reports.download' => 'Downloaded a report',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $admin = $request->user();
        $route = $request->route()?->getName();

        if (! $admin || ! $route || $response->getStatusCode() >= 400) {
            return $response;
        }

        if ($request->isMethodSafe()) {
            if (isset(self::DOWNLOADS[$route])) {
                ActivityLog::record($admin, self::DOWNLOADS[$route], $this->subject($request), null, $route);
            }

            return $response;
        }

        // Refused (a validation error, or "you cannot delete your own account"): nothing changed.
        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];

        if (in_array('errors', $flashed, true) || in_array('error', $flashed, true)) {
            return $response;
        }

        $details = in_array('success', $flashed, true) ? (string) $request->session()->get('success') : null;

        ActivityLog::record($admin, $this->action($request, $route), $this->subject($request), $details, $route);

        return $response;
    }

    private function action(Request $request, string $route): string
    {
        return match ($route) {
            'admin.destinations.store'  => $request->filled('church_id') ? 'Edited a destination' : 'Added a destination',
            'admin.schedules.store'     => $request->filled('schedule_id') ? 'Edited a schedule' : 'Added a schedule',
            'admin.system.maintenance'  => $request->input('state') === 'off' ? 'Turned maintenance mode off' : 'Turned on or updated maintenance mode',
            default                     => self::ACTIONS[$route] ?? 'Admin action ('.$route.')',
        };
    }

    /** Who or what the action was about, in words an admin would recognise. */
    private function subject(Request $request): ?string
    {
        foreach ($request->route()->parameters() as $value) {
            $name = match (true) {
                $value instanceof User         => $value->name.' ('.$value->email.')',
                $value instanceof Church       => $value->name,
                $value instanceof Schedule     => $value->event_name,
                $value instanceof Announcement => $value->title,
                $value instanceof Feedback     => 'Review #'.$value->id,
                is_string($value)              => $value,
                default                        => null,
            };

            if ($name !== null) {
                return $name;
            }
        }

        foreach (['name', 'event_name', 'title'] as $field) {
            if (is_string($request->input($field)) && $request->filled($field)) {
                return $request->input($field);
            }
        }

        return null;
    }
}
