<?php

namespace App\Http\Controllers;

use App\Models\AppFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** "Send feedback": how GIYA is working for someone. */
class AppFeedbackController extends Controller
{
    public function create(): View
    {
        return view('feedback', ['topics' => AppFeedback::FORM_TOPICS]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_feedback')) {
            return back()->with('error', __('giya.app_feedback.not_ready'));
        }

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'between:1,5'],
            'topic'   => ['required', 'in:'.implode(',', AppFeedback::FORM_TOPICS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => __('giya.app_feedback.need_rating'),
        ]);

        AppFeedback::create($data + ['user_id' => $request->user()->id, 'created_at' => now()]);

        return redirect()->route('app-feedback')->with('success', __('giya.app_feedback.thanks'));
    }
}
