@props(['title', 'updated', 'sections' => []])
{{-- Shared layout for the Privacy Policy and Terms: a readable column with a contents list. --}}
<div class="legal-page">
    <header class="legal-head">
        <h1 class="legal-title">{{ $title }}</h1>
        <p class="legal-updated">{{ __('giya.legal.updated', ['date' => $updated]) }}</p>
        @if (__('giya.legal.english_note'))
            <p class="legal-note">{{ __('giya.legal.english_note') }}</p>
        @endif
    </header>

    <nav class="legal-toc" aria-label="{{ __('giya.legal.contents') }}">
        <div class="legal-toc-title">{{ __('giya.legal.contents') }}</div>
        <ol>
            @foreach ($sections as $id => $heading)
                <li><a href="#{{ $id }}">{{ $heading }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="legal-body">
        {{ $slot }}
    </div>
</div>
