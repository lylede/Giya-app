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

        return view('admin.schedules', [
            'schedules' => $this->filtered($request)->paginate($perPage)->withQueryString(),
            'churches'  => Church::orderBy('name')->get(),
            'types'     => $this->types,
            'days'      => $this->days,
            'perPage'   => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

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
                    $s->location,
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
            'church_id'        => ['required', 'exists:churches,id'],
            'event_name'       => ['required', 'string', 'max:200'],
            'event_type'       => ['required', 'in:'.implode(',', $this->types)],
            'time_frame_label' => ['nullable', 'string', 'max:100'],
            'schedule_date'    => ['nullable', 'date'],
            'start_time'       => ['nullable', 'date_format:H:i'],
            'end_time'         => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'         => ['nullable', 'string', 'max:150'],
            'status'           => ['required', 'in:Published,Draft'],
            'repeat_type'      => ['nullable', 'in:none,daily,weekly,monthly,yearly'],
            'repeat_days'      => ['nullable', 'array', 'required_if:repeat_type,weekly'],
            'repeat_days.*'    => ['integer', 'between:0,6'],
            'repeat_nth'       => ['nullable', 'required_if:repeat_type,monthly,yearly', 'in:1,2,3,4,-1'],
            'repeat_weekday'   => ['nullable', 'required_if:repeat_type,monthly,yearly', 'integer', 'between:0,6'],
            'repeat_month'     => ['nullable', 'required_if:repeat_type,yearly', 'integer', 'between:1,12'],
            'notes'            => ['nullable', 'string'],
        ], [
            'end_time.after'          => 'End time must be later than the start time.',
            'repeat_days.required_if' => 'Pick at least one day of the week.',
            'repeat_nth.required_if'  => 'Pick which week (1st, 2nd, 3rd, 4th or last).',
            'repeat_weekday.required_if' => 'Pick the day of the week.',
            'repeat_month.required_if'   => 'Pick the month.',
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

        $rule = Recurrence::fromInput(
            $data['repeat_type'] ?? null, $data['repeat_days'] ?? [],
            $data['repeat_nth'] ?? null, $data['repeat_weekday'] ?? null, $data['repeat_month'] ?? null,
        );
        unset($data['repeat_type'], $data['repeat_days'], $data['repeat_nth'], $data['repeat_weekday'], $data['repeat_month']);

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
