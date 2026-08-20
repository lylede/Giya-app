@extends('layouts.auth')

@section('title', 'Reset Password')
@section('heading', 'Reset password')
@section('subheading', "We'll send a reset link to your email")

@section('content')
    <livewire:auth.forgot-password />
@endsection
