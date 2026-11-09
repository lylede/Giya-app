@extends('layouts.admin')
@section('title', 'Email check')
@section('page-title', 'Email check')
@section('page-subtitle', 'Is GIYA able to send email? Sign-up confirmation and password reset need it.')

@section('content')
<div class="card card-body mb-3">
    <div class="card-title">Current settings</div>
    <table class="giya-table">
        <tbody>
            @foreach ($rows as $label => $value)
                <tr><td style="width:40%">{{ $label }}</td><td><strong>{{ $value }}</strong></td></tr>
            @endforeach
        </tbody>
    </table>
    @if ($hint)
        <p class="mail-hint"><i class="bi bi-info-circle"></i> {{ $hint }}</p>
    @endif
</div>

<div class="card card-body">
    <div class="card-title">Send a test email</div>
    <p style="color:var(--text-muted);font-size:.875rem;margin:0 0 12px">Sends a short email to <strong>{{ auth()->user()->email }}</strong>.</p>

    @if (session('mail_error'))
        @php($err = session('mail_error'))
        <div class="mail-error" role="alert">
            <strong>Not sent ({{ $err['type'] }}, after {{ $err['seconds'] }}s)</strong>
            <code>{{ $err['message'] }}</code>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.mail.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary"><i class="bi bi-envelope"></i> Send test email</button>
    </form>
</div>
@endsection
