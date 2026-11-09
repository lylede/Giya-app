@extends('layouts.app')
@section('title', __('giya.legal.privacy'))

@php
    $contact = config('app.contact_email');
    $sections = [
        'collect'   => '1. What GIYA collects',
        'use'       => '2. Why GIYA uses it',
        'location'  => '3. Your location',
        'ai'        => '4. Giya AI chats',
        'payments'  => '5. Payments',
        'share'     => '6. Who else handles your data',
        'keep'      => '7. How long GIYA keeps it',
        'rights'    => '8. Your rights',
        'security'  => '9. How GIYA protects it',
        'contact'   => '10. Contact',
    ];
@endphp

@section('content')
<x-legal-page :title="__('giya.legal.privacy')" updated="10 November 2026" :sections="$sections">
    <p>GIYA is a pilgrimage companion for the churches of Cebu. This policy explains what personal data GIYA keeps, why, and what you can do about it, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2 id="collect">1. What GIYA collects</h2>
    <ul>
        <li><strong>Account:</strong> your name, email address and password. The password is stored only as a secure hash; nobody at GIYA can read it.</li>
        <li><strong>Profile:</strong> a photo if you upload one, your language and theme, and your reminder settings.</li>
        <li><strong>Your pilgrimages:</strong> itineraries you save, churches you mark as visited, favourites, churches you follow, and the ratings and reviews you write.</li>
        <li><strong>Giya AI:</strong> the messages you send to Giya AI and its replies.</li>
        <li><strong>Payments:</strong> the plan you bought, the amount, the date and the payment reference. Card and wallet details stay with Maya; GIYA never sees or stores them.</li>
        <li><strong>Technical:</strong> your sign-in session, and your IP address and browser for security (for example, to stop too many sign-in attempts).</li>
    </ul>

    <h2 id="use">2. Why GIYA uses it</h2>
    <ul>
        <li>To run your account and show you your itineraries, visits and favourites.</li>
        <li>To send the Mass and event reminders you ask for.</li>
        <li>To answer your questions in Giya AI.</li>
        <li>To process Premium payments.</li>
        <li>To keep GIYA safe and working, and to improve it using totals that do not identify you (for example, which churches are visited most).</li>
    </ul>
    <p>GIYA does not sell your data and does not show advertising.</p>

    <h2 id="location">3. Your location</h2>
    <p>The map and an active pilgrimage use your device's location, only after your browser asks you and you allow it. Your location is used on your device to show where you are and to notice when you reach a church. GIYA's server does not keep a history of where you have been; it only records that you reached a stop.</p>

    <h2 id="ai">4. Giya AI chats</h2>
    <p>To answer you, your message and the recent messages of that conversation are sent to Groq, the AI service GIYA uses. Please do not share private details such as ID numbers, card numbers or health information with Giya AI. Your chats are saved in your account so you can come back to them.</p>

    <h2 id="payments">5. Payments</h2>
    <p>Premium is paid through Maya. You enter your card or wallet details on Maya's own page, and Maya's privacy policy applies to them. GIYA receives only whether the payment succeeded and its reference.</p>

    <h2 id="share">6. Who else handles your data</h2>
    <p>GIYA uses these services to run, and they process data only to provide their service to GIYA:</p>
    <ul>
        <li><strong>Vercel</strong> and <strong>Render</strong>: host the website.</li>
        <li><strong>Neon</strong>: stores the database.</li>
        <li><strong>Supabase</strong>: stores uploaded photos.</li>
        <li><strong>Groq</strong>: answers Giya AI messages.</li>
        <li><strong>Maya</strong>: processes payments.</li>
        <li><strong>OpenStreetMap</strong> map tiles: show the map.</li>
    </ul>
    <p>Some of these services store data outside the Philippines. GIYA shares data with authorities only when the law requires it.</p>

    <h2 id="keep">7. How long GIYA keeps it</h2>
    <p>GIYA keeps your data while your account exists. When you delete your account, your profile, itineraries, visits, reviews, chats, notifications and payment records are deleted at once.</p>

    <h2 id="rights">8. Your rights</h2>
    <p>Under the Data Privacy Act you have the right to be informed, to access your data, to correct it, to object, to have it erased, and to file a complaint with the National Privacy Commission (privacy.gov.ph). In GIYA you can:</p>
    <ul>
        <li>see and change your name, email, photo and settings in <strong>Profile</strong>;</li>
        <li>delete itineraries, notifications and chats;</li>
        <li>delete your whole account in <strong>Profile, Preferences, Your data, Delete my account</strong>.</li>
    </ul>

    <h2 id="security">9. How GIYA protects it</h2>
    <p>GIYA uses HTTPS, hashes passwords, limits repeated sign-in attempts, keeps every API key on the server, and lets each account see only its own data. No system is perfectly secure; if a breach affects you, GIYA will tell you and the National Privacy Commission as the law requires.</p>

    <h2 id="contact">10. Contact</h2>
    @if ($contact)
        <p>For questions or requests about your data, email the GIYA team at <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
    @else
        <p>For questions or requests about your data, contact the GIYA team.</p>
    @endif
    <p>If this policy changes, the date at the top changes and GIYA will show a notice in the app.</p>
</x-legal-page>
@endsection
