@extends('layouts.auth')

@section('title', 'Set New Password')
@section('heading', 'Set new password')
@section('subheading', 'Choose a strong password for your account')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="field">
            <label class="form-label">Email Address</label>
            <input type="email" class="giya-input" value="{{ $email }}" disabled>
        </div>

        <div class="field">
            <label class="form-label" for="password">New Password</label>
            <div class="input-wrap">
                <input id="password" type="password" name="password" class="giya-input"
                       placeholder="Minimum 8 characters" required autofocus autocomplete="new-password">
                <button type="button" class="input-suffix" onclick="giyaTogglePassword('password', this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="form-label" for="password_confirmation">Confirm New Password</label>
            <div class="input-wrap">
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="giya-input" placeholder="Re-enter new password" required autocomplete="new-password">
                <button type="button" class="input-suffix" onclick="giyaTogglePassword('password_confirmation', this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-w-full">Reset Password</button>
    </form>
@endsection
    <livewire:auth.reset-password :token="$token" :email="$email" />
