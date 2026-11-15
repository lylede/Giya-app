@extends('layouts.app')
@section('title', __('giya.app_feedback.title'))

@section('content')
<div class="legal-page app-fb-page">
    <h1 class="legal-title">{{ __('giya.app_feedback.title') }}</h1>
    <p class="app-fb-lead">{{ __('giya.app_feedback.lead') }}</p>

    <form method="POST" action="{{ route('app-feedback.store') }}" class="app-fb-form card card-body">
        @csrf

        <fieldset class="app-fb-field">
            <legend class="form-label">{{ __('giya.app_feedback.rating_label') }}</legend>
            {{-- Five radio buttons drawn as stars: keyboard and screen readers get real choices. --}}
            <div class="app-fb-stars">
                @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" name="rating" id="fb-star-{{ $i }}" value="{{ $i }}" @checked((int) old('rating') === $i) required>
                    <label for="fb-star-{{ $i }}" title="{{ __('giya.app_feedback.star_'.$i) }}">
                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ __('giya.app_feedback.star_'.$i) }}</span>
                    </label>
                @endfor
            </div>
            @error('rating')<span class="field-error">{{ $message }}</span>@enderror
        </fieldset>

        <fieldset class="app-fb-field">
            <legend class="form-label">{{ __('giya.app_feedback.topic_label') }}</legend>
            <div class="app-fb-topics">
                @foreach ($topics as $topic)
                    <input type="radio" name="topic" id="fb-topic-{{ $topic }}" value="{{ $topic }}" @checked(old('topic', 'other') === $topic)>
                    <label for="fb-topic-{{ $topic }}">{{ __('giya.app_feedback.topic_'.$topic) }}</label>
                @endforeach
            </div>
            @error('topic')<span class="field-error">{{ $message }}</span>@enderror
        </fieldset>

        <div class="app-fb-field">
            <label class="form-label" for="fb-comment">{{ __('giya.app_feedback.comment_label') }}</label>
            <textarea id="fb-comment" name="comment" class="giya-input" rows="5" maxlength="1000"
                      placeholder="{{ __('giya.app_feedback.comment_ph') }}">{{ old('comment') }}</textarea>
            @error('comment')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <button type="submit" class="btn btn-primary app-fb-send">{{ __('giya.app_feedback.send') }}</button>
    </form>

    <p class="app-fb-note">{{ __('giya.app_feedback.church_note') }}</p>
</div>
@endsection
