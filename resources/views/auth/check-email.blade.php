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
        <form method="POST" action="{{ route('verification.code-pending') }}" class="verify-code-form">
            @csrf
            <label for="verify-code" class="verify-code-label">{{ __('giya.verify.code_label') }}</label>
            <div class="verify-code-row">
                <input id="verify-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="7" pattern="[0-9 ]*" placeholder="123456" class="giya-input verify-code-input" required>
                <button type="submit" class="btn btn-primary">{{ __('giya.verify.code_button') }}</button>
            </div>
            @error('code')<p class="field-error verify-code-error">{{ $message }}</p>@enderror
        </form>
        <a href="{{ route('login') }}" class="btn btn-primary btn-w-full">{{ __('giya.verify.go_sign_in') }}</a>
        <form method="POST" action="{{ route('verification.resend-pending') }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="btn btn-outline btn-w-full">{{ __('giya.verify.resend') }}</button>
        </form>
        <p class="verify-muted" style="margin-top:12px">{{ __('giya.verify.spam') }}</p>
    </div>
</div>
@endsection
