{{--
    Search and link previews (Google, Facebook, Messenger, Viber).
    A page can set its own with @section('meta_description', ...) and
    @section('meta_image', absolute URL); otherwise GIYA's defaults are used.
--}}
@php
    $metaTitle = 'GIYA - '.trim($__env->yieldContent('title', 'Pilgrimage Companion'));
    $metaDescription = trim($__env->yieldContent('meta_description'))
        ?: 'Find churches in Cebu, see Mass times and events, plan a pilgrimage or Visita Iglesia, and ask Giya AI. Your pilgrimage companion for Cebu.';
    $metaImage = trim($__env->yieldContent('meta_image')) ?: asset('images/brand/giya-share.jpg');
@endphp
<meta name="description" content="{{ \Illuminate\Support\Str::limit($metaDescription, 160) }}">
<meta name="theme-color" content="#8E3B2F">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GIYA">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($metaDescription, 200) }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $metaImage }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale() === 'en' ? 'en_PH' : (app()->getLocale() === 'fil' ? 'fil_PH' : 'ceb_PH')) }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
@if (request()->is('admin', 'admin/*', 'profile', 'chatbot*', 'plan/*'))
    <meta name="robots" content="noindex">
@endif
