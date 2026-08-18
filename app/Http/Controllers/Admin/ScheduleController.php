<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Schedule;
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
            $message = 'Schedule updated.';
        } else {
            unset($data['schedule_id']);
            Schedule::create($data + ['created_at' => now(), 'updated_at' => now()]);
            $message = 'Schedule added.';
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

            fputcsv($out, ['No.', 'Destination', 'Event Name', 'Type', 'Day / Date',
                           'Time Frame Label', 'Start Time', 'End Time',
                           'Whole Day', 'Location', 'Status']);

            foreach ($rows as $i => $s) {
                fputcsv($out, [
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
            'recurrence'       => ['nullable', 'string', 'max:50'],
            'notes'            => ['nullable', 'string'],
        ], [
            'end_time.after' => 'End time must be later than the start time.',
        ]);

        $data['is_whole_day'] = $request->boolean('is_whole_day');
        $data['is_recurring'] = filled($data['recurrence'] ?? null);

        if ($data['is_whole_day']) {
            $data['start_time'] = null;
            $data['end_time']   = null;
        }

        return $data;
    }
}
