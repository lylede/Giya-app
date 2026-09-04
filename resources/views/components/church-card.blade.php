@props(['church'])

<article class="church-card">
    <div class="church-card-img-wrap">
        <img src="{{ $church->imagePath() }}" alt="{{ $church->name }}" class="church-card-img" loading="lazy">
        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(36,28,24,0.55),transparent 55%)"></div>
        <span class="badge badge-primary" style="position:absolute;top:12px;left:12px">{{ $church->category }}</span>
        <span class="badge badge-gold" style="position:absolute;top:12px;right:52px">
            <img src="{{ asset('images/icons/star.svg') }}" alt="" width="11" height="11">
            {{ number_format($church->rating, 1) }}
        </span>
        @auth
            <button type="button" @class(['fav-btn', 'is-saved' => $church->isFavorited()])
                    data-fav="{{ $church->id }}"
                    aria-label="{{ __('giya.misc.save_church', ['church' => $church->name]) }}"
                    onclick="GiyaFav.toggle(this)">
                <i class="bi bi-heart-fill"></i>
            </button>
        @endauth
    </div>

    <div class="church-card-body">
        <h3 class="church-card-name">
            <a href="{{ route('churches.show', $church) }}">{{ $church->name }}</a>
        </h3>
        <p class="d-flex align-items-center gap-1" style="font-size: 0.75rem;color:var(--text-muted);margin:4px 0 0">
            <img src="{{ asset('images/icons/location.svg') }}" alt="" width="11" height="11">
            {{ $church->location }}
        </p>
        <p style="font-size: 0.75rem;color:var(--text-muted);line-height:1.6;margin:8px 0 0">
            {{ \Illuminate\Support\Str::limit($church->description, 88) }}
        </p>
        <div class="d-flex align-items-center justify-content-between mt-3">
            <span style="font-size: 0.6875rem;color:var(--primary);font-weight:700">{{ $church->daily_visits ?? '-' }} visitors</span>
            <a href="{{ route('map') }}" style="font-size: 0.6875rem;color:var(--primary);font-weight:700">{{ __('giya.church.view_on_map') }} →</a>
        </div>
    </div>
</article>

@auth
    @once
        @push('scripts')
        <script>
        window.GiyaFav = {
            toggle(btn) {
                btn.disabled = true;
                fetch('{{ route('favorites.toggle') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ church_id: Number(btn.dataset.fav) }),
                })
                    .then(r => r.json())
                    .then(data => { if (data.ok) btn.classList.toggle('is-saved', data.saved); })
                    .finally(() => { btn.disabled = false; });
            },
        };
        </script>
        @endpush
    @endonce
@endauth
