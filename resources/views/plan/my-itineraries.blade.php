@extends('layouts.app')
@section('title', __('giya.plan.my_title'))

@section('content')
<div class="page-wrap">

    <a href="{{ route('plan.hub') }}" class="back-link">
        <i class="bi bi-chevron-left"></i> {{ __('giya.plan.back_hub') }}
    </a>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 style="font-family:var(--font-display);font-size: 1.75rem;margin:0 0 4px">{{ __('giya.plan.my_title') }}</h1>
            <p style="color:var(--text-muted);font-size: 0.875rem;margin:0">{{ __('giya.plan.my_lead') }}</p>
        </div>

        @if ($atLimit)
            <button type="button" class="btn btn-primary" disabled title="{{ __('giya.plan.limit_title') }}">
                <i class="bi bi-lock-fill"></i> {{ __('giya.plan.limit_reached') }}
            </button>
        @else
            <a href="{{ route('plan.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> {{ __('giya.plan.create_new') }}
            </a>
        @endif
    </div>

    {{-- Usage meter --}}
    <div @class(['card', 'card-body', 'mb-4']) style="{{ $atLimit ? 'background:rgba(142,59,47,0.04);border-color:rgba(142,59,47,0.25)' : '' }}">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <div style="flex:1;min-width:240px">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span style="font-size: 0.875rem;font-weight:700;color:var(--text)">{{ __('giya.plan.free_usage') }}</span>
                    <span style="font-size: 0.875rem;font-weight:700;color:{{ $atLimit ? 'var(--primary)' : 'var(--text)' }}">
                        {{ $used }} / {{ $limit }}
                    </span>
                </div>
                <div style="height:12px;border-radius:999px;overflow:hidden;background:#F5E8D0">
                    <div style="height:100%;border-radius:999px;transition:width .5s;width:{{ min(($used / $limit) * 100, 100) }}%;
                                background:{{ $atLimit ? 'linear-gradient(to right,#8E3B2F,#C04030)' : 'linear-gradient(to right,#D7A94A,#F0C76C)' }}"></div>
                </div>
                <p style="font-size: 0.75rem;color:var(--text-muted);margin:6px 0 0">
                    @if ($atLimit)
                        {{ __('giya.plan.at_limit_note') }}
                    @else
                        {{ __('giya.plan.slots_left', ['count' => max(0, $limit - $used), 'limit' => $limit]) }}
                    @endif
                </p>
            </div>

            @if ($atLimit)
                {{-- This badge told devotees to upgrade and led nowhere. --}}
                <a href="{{ route('upgrade') }}" class="btn btn-primary"
                   style="padding:10px 18px;font-size: 0.75rem;flex:none;white-space:nowrap">
                    <i class="bi bi-gem"></i><span>{{ __('giya.plan.upgrade_unl') }}</span>
                </a>
            @else
                <span class="badge badge-amber" style="padding:10px 18px;font-size: 0.75rem">{{ __('giya.plan.free_active') }}</span>
            @endif
        </div>
    </div>

    {{-- Cards --}}
    @if ($itineraries->isEmpty())
        <div class="card">
            <x-empty-state icon="giya-route" :title="__('giya.plan.no_itineraries')"
                           :desc="__('giya.plan.no_itin_desc')">
                <a href="{{ route('plan.create') }}" class="btn btn-primary mt-3">{{ __('giya.plan.create_first') }}</a>
            </x-empty-state>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px" class="itin-grid">
            @foreach ($itineraries as $itinerary)
                <article class="card" style="overflow:hidden">
                    <div style="height:6px;background:{{ $itinerary->type === 'Visita Iglesia' ? 'var(--gold)' : 'var(--primary)' }}"></div>
                    <div style="padding:20px">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge badge-brown">
                                <i class="bi bi-{{ $itinerary->type === 'Visita Iglesia' ? 'giya-seven' : 'giya-route' }}"></i>
                                {{ $itinerary->type }}
                            </span>
                            <span class="badge status-{{ $itinerary->status }}">{{ $itinerary->status }}</span>
                        </div>

                        <h3 style="font-size: 1rem;font-weight:700;color:var(--text);margin:0 0 8px">{{ $itinerary->name }}</h3>

                        <div class="d-flex align-items-center gap-3 mb-3" style="font-size: 0.75rem;color:var(--text-muted)">
                            <span><i class="bi bi-geo-alt-fill"></i> {{ __('giya.plan.stops_count', ['count' => $itinerary->total_stops]) }}</span>
                            @if ($itinerary->scheduled_date)
                                <span><i class="bi bi-calendar3"></i> {{ $itinerary->scheduled_date->format('M j, Y') }}</span>
                            @endif
                        </div>

                        @if ($itinerary->stops->isNotEmpty())
                            <div style="margin-bottom:14px">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span style="font-size: 0.6875rem;color:var(--text-muted)">{{ __('giya.common.progress') }}</span>
                                    <span style="font-size: 0.6875rem;font-weight:700;color:var(--primary)">{{ $itinerary->progressPercent() }}%</span>
                                </div>
                                <div style="height:6px;border-radius:999px;background:#F5E8D0;overflow:hidden">
                                    <div style="height:100%;background:var(--gold);width:{{ $itinerary->progressPercent() }}%"></div>
                                </div>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:14px">
                                @foreach ($itinerary->stops->take(3) as $stop)
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width:6px;height:6px;border-radius:50%;flex-shrink:0;background:{{ $stop->is_visited ? 'var(--primary)' : 'var(--gold)' }}"></span>
                                        <span style="font-size: 0.6875rem;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $stop->church_name }}</span>
                                    </div>
                                @endforeach
                                @if ($itinerary->stops->count() > 3)
                                    <span style="font-size: 0.6875rem;color:var(--text-muted);padding-left:14px">
                                        +{{ $itinerary->stops->count() - 3 }} more
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="d-flex gap-2" style="padding-top:14px;border-top:1px solid var(--border-light)">
                            <a href="{{ route('plan.show', $itinerary) }}" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">
                                {{ $itinerary->status === 'Completed' ? __('giya.plan.act_view') : ($itinerary->status === 'Active' ? __('giya.plan.act_continue') : __('giya.plan.act_start')) }}
                            </a>
                            <button type="button" class="btn btn-danger btn-sm"
                                    onclick="GiyaDelete.open({{ $itinerary->id }}, @js($itinerary->name))">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach

            @if ($atLimit)
                <div style="border-radius:var(--radius-xl);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:32px;border:2px dashed rgba(142,59,47,.2);background:rgba(142,59,47,.02);min-height:240px;text-align:center">
                    <span style="width:56px;height:56px;border-radius:16px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-lock-fill" style="font-size: 1.375rem;color:var(--primary)"></i>
                    </span>
                    <div>
                        <p style="font-size: 0.875rem;font-weight:700;color:var(--text);margin:0 0 6px">{{ __('giya.plan.unlock_more') }}</p>
                        <p style="font-size: 0.75rem;color:var(--text-muted);line-height:1.6;margin:0">
                            {{ __('giya.plan.unlock_desc', ['limit' => $limit]) }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>

{{-- Delete confirmation --}}
<div class="modal" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px">
            <div class="modal-title"><i class="bi bi-trash3-fill" style="color:#D4183D"></i> {{ __('giya.plan.delete_q') }}</div>
            <p style="font-size: 0.8125rem;color:var(--text-muted);line-height:1.7;margin:0 0 20px">
                {!! __('giya.plan.delete_body', ['name' => '<span id="deleteName" style="font-weight:700;color:var(--text)"></span>']) !!}
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>{{ __('giya.common.cancel') }}</button>
                <form id="deleteForm" method="POST" style="flex:1">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-w-full"
                            style="background:#D4183D;color:#fff;border-radius:var(--radius-xl);padding:13px">
                        {{ __('giya.common.delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    @media (max-width: 1024px) { .itin-grid { grid-template-columns: repeat(2, 1fr) !important; } }
    @media (max-width: 660px)  { .itin-grid { grid-template-columns: 1fr !important; } }
</style>
@endpush

@push('scripts')
<script>
const GiyaDelete = {
    open(id, name) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteForm').action = '{{ url('plan') }}/' + id;
        GiyaUI.Modal.open('deleteModal');
    }
};
</script>
@endpush
@endsection
