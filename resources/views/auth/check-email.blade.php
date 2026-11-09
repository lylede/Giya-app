@extends('layouts.app')
@section('title', __('giya.verify.check_title'))

@section('content')
<div class="verify-page">
    <div class="verify-card card card-body">
        <div class="verify-icon"><i class="bi bi-envelope"></i></div>
        <h1 class="verify-title">{{ __('giya.verify.check_title') }}</h1>
        <p>{{ __('giya.verify.lead', ['email' => $email]) }}</p>
        <ol class="verify-steps">
            <li>{{ __('giya.verify.step_1') }}</li>
            <li>{{ __('giya.verify.step_2') }}</li>
            <li>{{ __('giya.verify.step_3') }}</li>
        </ol>
        <a href="{{ route('login') }}" class="btn btn-primary btn-w-full">{{ __('giya.verify.go_sign_in') }}</a>
        <form method="POST" action="{{ route('verification.resend-pending') }}" class="verify-resend" data-sending="{{ __('giya.verify.sending') }}">
            @csrf
            <button type="submit" class="btn btn-outline btn-w-full">{{ __('giya.verify.resend') }}</button>
        </form>
        <p class="verify-muted" style="margin-top:12px">{{ __('giya.verify.spam') }}</p>
    </div>
</div>
@endsection
