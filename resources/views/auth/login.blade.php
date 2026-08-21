@extends('layouts.auth')

@section('title', 'Sign In')
@section('heading', 'Welcome back')
@section('subheading', 'Sign in to continue your pilgrimage journey')

@section('content')
    <livewire:auth.login />
@endsection

