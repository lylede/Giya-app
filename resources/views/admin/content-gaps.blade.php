@extends('layouts.admin')
@section('title', 'Content Gaps')
@section('page-title', 'Content Gaps')
@section('page-subtitle', 'Published destinations still missing something pilgrims need · '.$complete.' of '.$total.' complete')

@section('content')

<div class="stat-row">
    @foreach ([
        'mass'    => ['calendar-x',  'primary'],
        'photo'   => ['camera-fill', 'gold'],
        'history' => ['journal-text','blue'],
        'contact' => ['telephone',   'green'],
    ] as $key => [$icon, $tone])
        <a href="{{ route('admin.gaps', ['gap' => $key]) }}" @class(['gap-tile', 'is-active' => $gap === $key])>
            <x-stat-tile :icon="$icon" :label="$gaps[$key]" :value="$counts[$key]" :tone="$tone" />
        </a>
    @endforeach
</div>

<div class="card card-body">
    <div class="card-title d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>{{ $gaps[$gap] }} <span class="gap-count">{{ $rows->count() }}</span></span>
        <span class="gap-help">
            @switch($gap)
                @case('mass') These show "usual times" to pilgrims. Add the real times from the parish's Facebook page, a call, or a visit. @break
                @case('photo') These show the placeholder. Upload a photo the team took or one you have permission to use. @break
                @case('history') Giya AI and the church page have nothing to tell about these. @break
                @case('contact') Pilgrims cannot call or message these parishes from GIYA. @break
            @endswitch
        </span>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state icon="check-circle-fill" title="Nothing missing here" desc="Every published destination has this. Salamat!" />
    @else
        <div class="table-responsive">
            <table class="giya-table gap-table">
                <thead>
                    <tr><th>Destination</th><th>Town</th><th>Part of Cebu</th><th>Also missing</th><th class="text-end">Fix</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="gap-name">{{ $row['church']->name }}</td>
                            <td>{{ $row['town'] }}</td>
                            <td><span class="gap-region">{{ ucwords(strtolower(strtok($row['region'], '('))) }}</span></td>
                            <td>
                                @foreach (array_diff($row['missing'], [$gap]) as $other)
                                    <a href="{{ route('admin.gaps', ['gap' => $other]) }}" class="gap-chip">{{ \Illuminate\Support\Str::after($gaps[$other], 'No ') }}</a>
                                @endforeach
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($gap === 'mass')
                                    <a href="{{ route('admin.schedules', ['church_id' => $row['church']->id]) }}" class="btn btn-primary btn-sm">Add Mass times</a>
                                @else
                                    <a href="{{ route('admin.destinations', ['search' => $row['church']->name]) }}" class="btn btn-primary btn-sm">Edit destination</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
