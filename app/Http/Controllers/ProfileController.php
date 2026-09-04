<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\User;
use App\Models\VisitHistory;
use App\Models\Favorite;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{  public function index(): View
    {
        $user = Auth::user();

        return view('profile', [
            'user'        => $user,
            // When their Premium runs out, so the overview can say so rather
            // than only that they have it. Null for a free account.
            'premiumUntil' => Transaction::premiumExpiryByUser([$user->id])[$user->id] ?? null,
            // Includes deleted itineraries: the free tier is three per account
            // for the life of the account, so deleting one does not give the
            // slot back. The list below deliberately excludes them.
            'itinerariesUsed' => Itinerary::countingAgainstFreeLimit($user->id),
            'visits'      => VisitHistory::with('church')
                                ->where('user_id', $user->id)
                                ->orderByDesc('visited_at')->get(),
            'itineraries' => Itinerary::with('itineraryType')
                                ->where('user_id', $user->id)
                                ->orderByDesc('created_at')->get(),
            'favorites'   => Favorite::with('church.churchCategory')
                                ->where('user_id', $user->id)
                                ->where('is_active', true)
                                ->orderByDesc('created_at')->get(),
            'reviewCount' => Feedback::where('user_id', $user->id)->count(),
            'reviewed'    => Feedback::where('user_id', $user->id)
                                ->pluck('rating', 'church_id'),
            'prefs'       => $user->preferencesOrDefault(),
        ]);
    }


    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'email'  => ['required', 'email', 'max:150', 'unique:devotees,email,' . $user->id],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [
            'email.unique' => 'That email address is already in use by another account.',
            'avatar.image' => 'Choose an image file (JPG, PNG, or WEBP).',
            'avatar.max'   => 'Keep the photo under 2 MB.',
        ]);

        $changes = [
            'name'       => $data['name'],
            'email'      => $data['email'],
            'updated_at' => now(),
        ];

        if ($request->hasFile('avatar')) {
            // Drop the old file so uploads don't pile up on disk.
            if ($user->avatar_url && ! str_starts_with($user->avatar_url, 'http')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $user->avatar_url));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $changes['avatar_url'] = 'storage/' . $path;
        }

        User::where('id', $user->id)->update($changes);

        return redirect()->route('profile')->with('success', 'Profile updated successfully.');
    }

    public function removeAvatar(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->avatar_url && ! str_starts_with($user->avatar_url, 'http')) {
            Storage::disk('public')->delete(str_replace('storage/', '', $user->avatar_url));
        }

        User::where('id', $user->id)->update(['avatar_url' => null, 'updated_at' => now()]);

        return redirect()->route('profile')->with('success', 'Photo removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Passwords do not match.',
        ]);

        if (! Hash::check($request->current_password, $user->password_hash)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        User::where('id', $user->id)->update([
            'password_hash' => Hash::make($request->password),
            'updated_at'    => now(),
        ]);

        return redirect()->route('profile')->with('success', 'Password changed successfully.');
    }
    /**
     * Save a single preference as soon as it changes.
     *
     * The full-form action stays for a browser without JavaScript; this is what
     * the auto-saving controls call. One field at a time, so a half-filled form
     * is never a problem.
     */
    public function updatePreference(Request $request): \Illuminate\Http\JsonResponse
    {
        $allowed = [
            'font_size'                => ['required', 'in:Small,Medium,Large'],
            'theme_style'              => ['required', 'in:Light,Dark'],
            'language'                 => ['required', 'in:English,Cebuano,Filipino'],
            'notify_mass_schedule'     => ['required', 'boolean'],
            'notify_itinerary'         => ['required', 'boolean'],
            'notify_feast_day'         => ['required', 'boolean'],
            'notify_saved_destination' => ['required', 'boolean'],
        ];

        $field = $request->input('field');

        if (! array_key_exists($field, $allowed)) {
            return response()->json(['ok' => false, 'reason' => 'unknown_field'], 422);
        }

        $data = $request->validate(['value' => $allowed[$field]], [], ['value' => $field]);

        $value = in_array($field, ['font_size', 'theme_style', 'language'], true)
            ? $data['value']
            : $request->boolean('value');

        Auth::user()->preferencesOrDefault()->update([
            $field       => $value,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'field' => $field, 'value' => $value]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'font_size'   => ['required', 'in:Small,Medium,Large'],
            'theme_style' => ['required', 'in:Light,Dark'],
            'language'    => ['required', 'in:English,Cebuano,Filipino'],
        ]);

        Auth::user()->preferencesOrDefault()->update($data + [
            'notify_mass_schedule'     => $request->boolean('notify_mass_schedule'),
            'notify_itinerary'         => $request->boolean('notify_itinerary'),
            'notify_feast_day'         => $request->boolean('notify_feast_day'),
            'notify_saved_destination' => $request->boolean('notify_saved_destination'),
            'updated_at'               => now(),
        ]);

        return redirect()->route('profile', ['tab' => 'preferences'])->with('success', 'Preferences saved.');
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'integer', 'exists:churches,id'],
        ]);

        $saved = Favorite::toggle(Auth::id(), $data['church_id']);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }
    public function storeReview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'visit_id' => ['required', 'integer', 'exists:visit_history,id'],
            'rating'   => ['required', 'integer', 'between:1,5'],
            'comment'  => ['nullable', 'string', 'max:2000'],
        ], [
            'rating.required' => 'Choose a star rating.',
        ]);

        $visit = VisitHistory::where('id', $data['visit_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        Feedback::updateOrCreate(
            ['user_id' => Auth::id(), 'visit_history_id' => $visit->id],
            [
                'church_id'  => $visit->church_id,
                'rating'     => $data['rating'],
                'comment'    => $data['comment'] ?? null,
                'status'     => 'Pending',
                'created_at' => now(),
            ]
        );

        return redirect()->route('profile')->with('success', 'Thank you for your review.');
    }
}
