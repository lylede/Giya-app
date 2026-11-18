<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Schedule;
use App\Support\Notifier;
use App\Support\Recurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleController extends Controller
{
    protected array $types = ['Mass', 'Novena', 'Feast Day', 'Procession', 'Celebration', 'Other'];

    protected array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday',
                             'Friday', 'Saturday', 'Sunday', 'Daily'];

    public function index(Request $request): View
    {
        Schedule::rollForwardDue();

        $perPage = in_array((int) $request->per_page, [5, 10, 25, 50], true)
            ? (int) $request->per_page
            : 10;

        /* Two ways in. "By church" is the one for editing: pick a church and
           see its week as a timetable, one row per Mass with a tick for each
           day. "All schedules" is the long list, for searching across every
           church and for changing many rows at once. The page opens on the
           list; a link that names a church (church_id) opens its timetable. */
        $view = $request->input('view');
        if (! in_array($view, ['church', 'all'], true)) {
            $view = $request->filled('church_id') && ! $request->filled('search') ? 'church' : 'all';
        }

        $churches = Church::orderBy('name')->get();
        $church   = $view === 'church' && $request->filled('church_id')
            ? $churches->firstWhere('id', (int) $request->church_id)
            : null;

        $weekly = collect();
        $dated  = collect();
        if ($church) {
            $all = Schedule::where('church_id', $church->id)
                ->orderByRaw('start_time NULLS LAST')->orderBy('event_name')->get();
            // A Mass that repeats on weekdays goes in the timetable; feast
            // days, novenas and one-off events go in the list under it.
            [$weekly, $dated] = $all->partition(fn (Schedule $s) => $s->weekdays() !== null);
            $dated = $dated->sortBy(fn (Schedule $s) => $s->schedule_date?->timestamp ?? PHP_INT_MAX)->values();
        }

        return view('admin.schedules', [
            'view'      => $view,
            'church'    => $church,
            'weekly'    => $weekly->values(),
            'dated'     => $dated,
            'counts'    => Schedule::selectRaw('church_id, COUNT(*) AS n')->groupBy('church_id')->pluck('n', 'church_id'),
            'schedules' => $view === 'all'
                ? $this->filtered($request)->paginate($perPage)->withQueryString()
                : null,
            'churches'  => $churches,
            'types'     => $this->types,
            'days'      => $this->days,
            'perPage'   => $perPage,
        ]);
    }

    /**
     * Timetable tick: add or remove one weekday on a repeating schedule.
     * Seven days make it Daily; the last day cannot be taken away (delete
     * the Mass instead), so a Mass never ends up on no day at all.
     */
    public function toggleDay(Request $request, Schedule $schedule): RedirectResponse
    {
        $day  = (int) $request->validate(['day' => ['required', 'integer', 'between:0,6']])['day'];
        $days = $schedule->weekdays() ?? [];

        $days = in_array($day, $days, true)
            ? array_values(array_diff($days, [$day]))
            : array_merge($days, [$day]);
        sort($days);

        if ($days === []) {
            return back()->with('warning', 'A Mass needs at least one day. To remove it completely, use the bin.');
        }

        $rule = count($days) === 7 ? ['type' => 'daily'] : Recurrence::fromInput('weekly', $days);

        $schedule->update([
            'recurrence_rule' => $rule,
            'recurrence'      => Recurrence::label($rule),
            'is_recurring'    => true,
            'schedule_date'   => null,
            'updated_at'      => now(),
        ]);

        return back()->with('success', $schedule->event_name.' now: '.Recurrence::label($rule).'.');
    }

    /**
     * Copy every schedule from one church to another. The copies arrive as
     * Drafts, so nobody sees them until the admin has checked the times and
     * published them.
     */
    public function copy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_church_id' => ['required', 'exists:churches,id', 'different:to_church_id'],
            'to_church_id'   => ['required', 'exists:churches,id'],
        ], ['from_church_id.different' => 'Pick a different church to copy from.']);

        $fromPlace = Church::find($data['from_church_id'])?->placeLabel();
        $copied = 0;
        foreach (Schedule::where('church_id', $data['from_church_id'])->get() as $s) {
            $new = $s->replicate();
            $new->church_id  = (int) $data['to_church_id'];
            // The first church's address is not this one's: it is filled in
            // again for the new church. A typed place ("Chapel") is kept.
            if ($new->location === $fromPlace) {
                $new->location = null;
            }
            $new->status     = 'Draft';
            $new->created_at = now();
            $new->updated_at = now();
            $new->save();
            $copied++;
        }

        return redirect()->route('admin.schedules', ['view' => 'church', 'church_id' => $data['to_church_id']])
            ->with('success', $copied === 0
                ? 'That church has no schedules to copy.'
                : "{$copied} ".($copied === 1 ? 'schedule' : 'schedules').' copied as Draft. Check the times, then publish them.');
    }

    /** Several rows at once: publish them, set them to Draft, or delete them. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'action' => ['required', 'in:publish,draft,delete'],
        ], ['ids.required' => 'Tick at least one schedule first.']);

        $rows = Schedule::whereIn('id', $data['ids']);
        $n    = (clone $rows)->count();

        match ($data['action']) {
            'publish' => $rows->update(['status' => 'Published', 'updated_at' => now()]),
            'draft'   => $rows->update(['status' => 'Draft', 'updated_at' => now()]),
            'delete'  => $rows->get()->each->delete(),
        };

        $done = ['publish' => 'published', 'draft' => 'set to Draft', 'delete' => 'removed'][$data['action']];

        return back()->with('success', "{$n} ".($n === 1 ? 'schedule' : 'schedules')." {$done}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // A Location left blank becomes the church's address (Schedule::booted).
        if (! empty($data['schedule_id'])) {
            $schedule = Schedule::findOrFail($data['schedule_id']);
            unset($data['schedule_id']);
            $schedule->update($data + ['updated_at' => now()]);

            /* getChanges() AFTER the update: what actually differs, not what
               was submitted. An admin who opens the form and presses Save
               without touching anything has changed nothing, and nobody
               should be told about it. */
            $told = Notifier::schedulePosted($schedule, $schedule->getChanges());
            $message = 'Schedule updated.';
        } else {
            unset($data['schedule_id']);
            $schedule = Schedule::create($data + ['created_at' => now(), 'updated_at' => now()]);
            $told = Notifier::schedulePosted($schedule);
            $message = 'Schedule added.';
        }

        /* Said out loud, because the alternative is an admin publishing a
           feast day and having no idea whether it reached anyone. */
        if ($told > 0) {
            $message .= " {$told} ".($told === 1 ? 'devotee was' : 'devotees were').' notified.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    /** CSV of whatever the current filters show. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->get();
        $name = 'giya-schedules-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            \App\Support\Csv::put($out, ['No.', 'Destination', 'Event Name', 'Type', 'Day / Date',
                           'Time Frame Label', 'Start Time', 'End Time',
                           'Whole Day', 'Location', 'Status']);

            foreach ($rows as $i => $s) {
                \App\Support\Csv::put($out, [
                    $i + 1,
                    $s->church->name ?? '',
                    $s->event_name,
                    $s->event_type,
                    $s->day_label,
                    $s->time_frame_label,
                    $s->timeLabel($s->start_time),
                    $s->timeLabel($s->end_time),
                    $s->is_whole_day ? 'Yes' : 'No',
                    $s->placeLabel(),
                    $s->status,
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------ */

    protected function filtered(Request $request)
    {
        return Schedule::with('church')
            ->when($request->search, function ($q, $term) {
                $term = '%'.mb_strtolower($term).'%';
                $q->where(function ($w) use ($term) {
                    $w->whereRaw('LOWER(event_name) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$term])
                      ->orWhereHas('church', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->when($request->church_id, fn ($q, $id) => $q->where('church_id', $id))
            ->when($request->event_type, fn ($q, $t) => $q->where('event_type', $t))
            ->when($request->day, fn ($q, $d) => $q->where(function ($w) use ($d) {
                $w->where('recurrence', 'ILIKE', '%'.$d.'%')
                  ->orWhereRaw("TO_CHAR(schedule_date, 'FMDay') = ?", [$d]);
            }))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('church_id')
            ->orderByRaw('start_time NULLS LAST');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'schedule_id'      => ['nullable', 'integer', 'exists:schedules,id'],
            // Empty for a town festival (no GIYA destination); then the town
            // goes in Location.
            'church_id'        => ['nullable', 'required_without:location', 'exists:churches,id'],
            'event_name'       => ['required', 'string', 'max:200'],
            'event_type'       => ['required', 'in:'.implode(',', $this->types)],
            'language'         => ['nullable', 'in:'.implode(',', \App\Models\Schedule::LANGUAGES)],
            'time_frame_label' => ['nullable', 'string', 'max:100'],
            'schedule_date'    => ['nullable', 'date'],
            'start_time'       => ['nullable', 'date_format:H:i'],
            'end_time'         => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'         => ['nullable', 'string', 'max:150'],
            'status'           => ['required', 'in:Published,Draft'],
            'repeat_type'      => ['nullable', 'in:none,daily,weekly,monthly,yearly'],
            'repeat_days'      => ['nullable', 'array', 'required_if:repeat_type,weekly'],
            'repeat_days.*'    => ['integer', 'between:0,6'],
            'repeat_yearly_on' => ['nullable', 'in:nth,date,easter'],
            'repeat_nth'       => ['nullable', 'in:1,2,3,4,-1'],
            'repeat_weekday'   => ['nullable', 'integer', 'between:0,6'],
            'repeat_month'     => ['nullable', 'integer', 'between:1,12'],
            'repeat_day'       => ['nullable', 'integer', 'between:1,31'],
            'notes'            => ['nullable', 'string'],
        ], [
            'end_time.after'          => 'End time must be later than the start time.',
            'church_id.required_without' => 'Pick a destination, or type the town in Location for a town festival.',
            'repeat_days.required_if' => 'Pick at least one day of the week.',
            'schedule_date.required_if'  => 'Pick the date, or choose how it repeats.',
        ]);

        /* How it repeats, as a rule the app can work dates out from, plus
           the readable label the rest of GIYA shows ("Yearly - 4th Sunday
           of January"). Monthly and yearly rules carry their next date in
           schedule_date, so lists, Giya AI and reminders treat them like
           any dated event. */
        // The old single "Repeats on" value (a day name or "Daily") still
        // works, for anything that posts the previous form.
        if (empty($data['repeat_type']) && filled($legacy = $request->input('recurrence'))) {
            $day = array_search(ucfirst(strtolower(trim($legacy))), Recurrence::WEEKDAYS, true);
            if (strcasecmp(trim($legacy), 'Daily') === 0) {
                $data['repeat_type'] = 'daily';
            } elseif ($day !== false) {
                $data['repeat_type'] = 'weekly';
                $data['repeat_days'] = [$day];
            }
        }

        $type = $data['repeat_type'] ?? null;
        $rule = Recurrence::fromInput(
            $type, $data['repeat_days'] ?? [],
            $data['repeat_nth'] ?? null, $data['repeat_weekday'] ?? null, $data['repeat_month'] ?? null,
            $data['repeat_yearly_on'] ?? 'nth', $data['repeat_day'] ?? null,
        );

        // A repeat was chosen but a part of it is missing: say which part.
        if (in_array($type, ['monthly', 'yearly'], true) && ! $rule) {
            $on = $data['repeat_yearly_on'] ?? 'nth';
            throw \Illuminate\Validation\ValidationException::withMessages(
                $type === 'yearly' && $on === 'date'
                    ? ['repeat_day' => 'Pick the month and a day that exists in it.']
                    : ($type === 'yearly' && empty($data['repeat_month'])
                        ? ['repeat_month' => 'Pick the month.']
                        : ['repeat_nth' => 'Pick which week (1st, 2nd, 3rd, 4th or last) and the day.'])
            );
        }
        unset($data['repeat_type'], $data['repeat_days'], $data['repeat_nth'], $data['repeat_weekday'],
              $data['repeat_month'], $data['repeat_yearly_on'], $data['repeat_day']);

        $data['recurrence_rule'] = $rule;
        $data['recurrence']      = $rule ? Recurrence::label($rule) : null;

        if ($rule) {
            $data['schedule_date'] = Recurrence::hasDates($rule) ? Recurrence::next($rule)->toDateString() : null;
        } elseif (empty($data['schedule_date'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'schedule_date' => 'Pick the date, or choose how it repeats.',
            ]);
        }

        $data['is_whole_day'] = $request->boolean('is_whole_day');
        $data['is_recurring'] = $rule !== null;

        if ($data['is_whole_day']) {
            $data['start_time'] = null;
            $data['end_time']   = null;
        }

        return $data;
    }
}
