{{--
    The one back button. Every page below Home uses it, so it looks and
    behaves the same everywhere: a round chevron plus "Back to <page>",
    top-left, at least 44px tall for a thumb.

    tone="dark"   on a photo or a dark bar (white instead of brown)
    compact       the chevron only, with the label kept for screen readers,
                  where a strip has no room for the words
--}}
@props(['href', 'label', 'tone' => 'light', 'compact' => false])

<a href="{{ $href }}"
   {{ $attributes->class(['giya-back', 'is-dark' => $tone === 'dark', 'is-compact' => $compact]) }}
   @if ($compact) aria-label="{{ $label }}" title="{{ $label }}" @endif>
    <span class="giya-back-icon" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
    <span class="giya-back-label">{{ $label }}</span>
</a>
