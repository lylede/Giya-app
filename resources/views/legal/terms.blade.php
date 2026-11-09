@extends('layouts.app')
@section('title', __('giya.legal.terms'))

@php
    $sections = [
        'about'     => '1. About GIYA',
        'account'   => '2. Your account',
        'info'      => '3. Church information',
        'ai'        => '4. Giya AI',
        'conduct'   => '5. Reviews and conduct',
        'premium'   => '6. Premium',
        'safety'    => '7. Your safety on the road',
        'liability' => '8. Limits',
        'changes'   => '9. Changes and ending',
    ];
@endphp

@section('content')
<x-legal-page :title="__('giya.legal.terms')" updated="10 November 2026" :sections="$sections">
    <p>By creating a GIYA account you agree to these terms and to the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

    <h2 id="about">1. About GIYA</h2>
    <p>GIYA helps pilgrims find churches in Cebu, see Mass times and events, plan routes, and ask Giya AI about churches and the faith. GIYA is not run by the Archdiocese of Cebu or by any parish.</p>

    <h2 id="account">2. Your account</h2>
    <ul>
        <li>Give your real name and an email address you use.</li>
        <li>Keep your password private. You are responsible for what is done with your account.</li>
        <li>One person, one account. GIYA may suspend an account that breaks these terms.</li>
        <li>You can delete your account at any time in Profile.</li>
    </ul>

    <h2 id="info">3. Church information</h2>
    <p>Mass times, events, contacts and histories come from parishes, the Archdiocese of Cebu, public pages and the GIYA team. Some Mass times are marked as usual times and may not be exact. Schedules change, especially on feast days and holidays, so confirm with the parish before travelling.</p>

    <h2 id="ai">4. Giya AI</h2>
    <p>Giya AI is an artificial intelligence. It can be wrong. Route times are estimates from the map, not live traffic. For matters of faith and the sacraments, your parish priest is the authority. Giya AI answers questions about churches, pilgrimages and the Catholic faith only.</p>

    <h2 id="conduct">5. Reviews and conduct</h2>
    <p>Reviews should be honest and about your own visit. Do not post anything offensive, false, or that names or harms other people. The GIYA team may hide reviews that break these rules. Do not try to break, overload or misuse GIYA.</p>

    <h2 id="premium">6. Premium</h2>
    <p>Premium gives the extra features shown on the Upgrade page for the period you pay for, through Maya. Prices are in Philippine pesos and shown before you pay. Premium does not renew by itself. If a payment fails or is cancelled, you are not charged.</p>

    <h2 id="safety">7. Your safety on the road</h2>
    <p>Do not use GIYA while driving. Follow traffic rules, dress and behave respectfully in churches, and take care of your health on long walks.</p>

    <h2 id="liability">8. Limits</h2>
    <p>GIYA is provided as it is, as a student project. The team works to keep it accurate and available but cannot promise it will always be correct or online, and is not responsible for losses from relying on a schedule or route that changed.</p>

    <h2 id="changes">9. Changes and ending</h2>
    <p>These terms may change; the date at the top shows the latest version and GIYA will show a notice in the app. You can stop using GIYA and delete your account at any time.</p>
</x-legal-page>
@endsection
