<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone switch: the browser hands GIYA the address and keys of its push
 * subscription (subscribe), takes them back (unsubscribe), and the devotee can
 * ask for one notification to check it works (test).
 */
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        abort_unless(WebPush::enabled(), 503);

        $data = $request->validate([
            'endpoint'    => ['required', 'string', 'max:1000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth'   => ['required', 'string', 'max:40'],
        ]);

        // A browser has one subscription; if someone else signs in on the
        // same phone, it moves to them.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])],
            [
                'user_id'    => $request->user()->id,
                'endpoint'   => $data['endpoint'],
                'p256dh'     => $data['keys']['p256dh'],
                'auth'       => $data['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        PushSubscription::where('endpoint_hash', PushSubscription::hashOf($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Send me a test. Answers with what each push service said, so the
     * Profile can say why it did not arrive instead of just "failed".
     */
    public function test(Request $request): JsonResponse
    {
        if (! WebPush::enabled()) {
            return response()->json(['ok' => false, 'sent' => 0, 'message' => __('giya.push.no_keys')]);
        }

        $subs = PushSubscription::where('user_id', $request->user()->id)->get();
        if ($subs->isEmpty()) {
            return response()->json(['ok' => false, 'sent' => 0, 'message' => __('giya.push.no_device')]);
        }

        $results = $subs->map(fn ($sub) => WebPush::report($sub, [
            'title' => __('giya.push.test_title'),
            'body'  => __('giya.push.test_body'),
            'url'   => route('profile', ['tab' => 'preferences']),
            'tag'   => 'test',
        ]))->values();

        $sent = $results->where('ok', true)->count();
        $failed = $results->firstWhere('ok', false);

        return response()->json([
            'ok'      => $sent > 0,
            'sent'    => $sent,
            'devices' => $subs->count(),
            'message' => $sent > 0
                ? __('giya.push.test_sent')
                : __('giya.push.refused', [
                    'service' => $failed['service'] ?? '',
                    'reason'  => trim(($failed['status'] ? $failed['status'].' ' : '').$failed['reason']),
                ]),
        ]);
    }
}
