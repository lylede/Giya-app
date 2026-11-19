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

    public function test(Request $request): JsonResponse
    {
        abort_unless(WebPush::enabled(), 503);

        $subs = PushSubscription::where('user_id', $request->user()->id)->get();
        $sent = 0;
        foreach ($subs as $sub) {
            $sent += WebPush::send($sub, [
                'title' => __('giya.push.test_title'),
                'body'  => __('giya.push.test_body'),
                'url'   => route('profile'),
                'tag'   => 'test',
            ]) ? 1 : 0;
        }

        return response()->json(['ok' => $sent > 0, 'sent' => $sent]);
    }
}
