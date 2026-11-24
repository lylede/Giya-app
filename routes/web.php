<?php

use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GIYA - Web Routes
|--------------------------------------------------------------------------
| Every internal link in the Blade templates resolves through a named route
| below. No route points at an external service.
*/

Route::get('/', [HomeController::class, 'landing'])->name('root');

/* ---------------------------------------------------------------- Guest */
Route::middleware('guest')->group(function () {
    Route::get('/welcome',        [\App\Http\Controllers\OnboardingController::class, 'show'])->name('onboarding');
    Route::get('/welcome/finish', [\App\Http\Controllers\OnboardingController::class, 'finish'])->name('onboarding.finish');

    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->middleware('throttle:20,1')->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->middleware('throttle:20,1')->name('auth.google.callback');

    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');

    Route::get('/register',  [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

    Route::get('/forgot-password',  [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password',        [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

/* ------------------------------------------------------- Authenticated */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/home',    [HomeController::class, 'index'])->name('home');
Route::get('/search/churches', [HomeController::class, 'search'])->middleware('throttle:giya-actions')->name('search.churches');
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot');
    Route::get('/chatbot/history', [ChatbotController::class, 'history'])->name('chatbot.history');
    Route::post('/chatbot/send',  [ChatbotController::class, 'send'])->middleware(['throttle:giya-chat', 'confirmed.email'])->name('chatbot.send');
    Route::post('/chatbot/reset', [ChatbotController::class, 'reset'])->name('chatbot.reset');
    // Chat history: open a past chat, continue it, delete one or all.
    Route::delete('/chatbot/history', [ChatbotController::class, 'clear'])->name('chatbot.clear');
    Route::get('/chatbot/c/{chat}', [ChatbotController::class, 'show'])->whereNumber('chat')->name('chatbot.show');
    Route::post('/chatbot/c/{chat}/continue', [ChatbotController::class, 'resume'])->whereNumber('chat')->name('chatbot.resume');
    Route::delete('/chatbot/c/{chat}', [ChatbotController::class, 'destroy'])->whereNumber('chat')->name('chatbot.destroy');

    /* Profile */
    Route::get('/profile',            [ProfileController::class, 'index'])->name('profile');
    Route::patch('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',         [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('profile.destroy');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::patch('/profile/preference',  [ProfileController::class, 'updatePreference'])->name('profile.preference');
    Route::post('/favorites/toggle',     [ProfileController::class, 'toggleFavorite'])->middleware('throttle:giya-actions')->name('favorites.toggle');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/review', [ProfileController::class, 'storeReview'])->middleware(['throttle:giya-actions', 'confirmed.email'])->name('profile.review');

    /* Plan / Itineraries - static segments registered before {itinerary} */
    Route::prefix('plan')->name('plan.')->controller(ItineraryController::class)->where(['itinerary' => '[0-9]+'])->group(function () {
        Route::get('/',                'hub')->name('hub');
        /* The custom planner is the map now. This stays only so a link
           someone already has - a bookmark, a message, an old screenshot -
           lands on the planner rather than on a 404, and it carries any
           stops through so nothing is lost on the way. */
        Route::get('/create', function (\Illuminate\Http\Request $request) {
            return redirect()->route('map', array_filter([
                'plan'  => 1,
                'stops' => $request->query('stops'),
            ]));
        })->name('create');
        Route::get('/visita-iglesia',  'visita')->name('visita');
        Route::get('/my-itineraries',  'index')->name('index');
        Route::post('/',               'store')->middleware('confirmed.email')->name('store');
        Route::post('/stop/visited',   'markVisited')->name('stop.visited');
        // withTrashed: an old notification can point at a trip deleted since; show() sends it on.
        Route::get('/{itinerary}',     'show')->withTrashed()->name('show');
        Route::post('/{itinerary}/ratings', 'storeRatings')->middleware('confirmed.email')->name('ratings');
        Route::delete('/{itinerary}',  'destroy')->name('destroy');
        // Change the stops of a saved trip, also mid-trip (Premium; ItineraryStopController).
        Route::post('/{itinerary}/stops', [\App\Http\Controllers\ItineraryStopController::class, 'add'])->middleware('throttle:giya-actions')->name('stops.add');
        Route::post('/{itinerary}/stops/order', [\App\Http\Controllers\ItineraryStopController::class, 'reorder'])->middleware('throttle:giya-actions')->name('stops.reorder');
        Route::post('/{itinerary}/stops/{stop}/swap', [\App\Http\Controllers\ItineraryStopController::class, 'swap'])->whereNumber('stop')->middleware('throttle:giya-actions')->name('stops.swap');
        Route::delete('/{itinerary}/stops/{stop}', [\App\Http\Controllers\ItineraryStopController::class, 'remove'])->whereNumber('stop')->middleware('throttle:giya-actions')->name('stops.remove');
        // Share with family or a parish group (SharedItineraryController).
        Route::post('/{itinerary}/share', [\App\Http\Controllers\SharedItineraryController::class, 'share'])->middleware('throttle:giya-actions')->name('share');
        Route::delete('/{itinerary}/share', [\App\Http\Controllers\SharedItineraryController::class, 'unshare'])->name('unshare');
    });
});

/* ------------------------------------------------------- Premium / Maya */
Route::middleware('auth')->group(function () {
    Route::get('/upgrade',                 [PaymentController::class, 'upgrade'])->name('upgrade');
    Route::post('/upgrade/{plan}/checkout',[PaymentController::class, 'checkout'])->middleware(['throttle:10,1', 'confirmed.email'])->name('upgrade.checkout');

    // Where Maya sends the devotee back to. They are behind auth because the
    // devotee is signed in throughout - Maya opens in the same browser.
    Route::get('/upgrade/success', [PaymentController::class, 'success'])->name('upgrade.success');
    Route::get('/upgrade/failure', [PaymentController::class, 'failure'])->name('upgrade.failure');
    Route::get('/upgrade/cancel',  [PaymentController::class, 'cancel'])->name('upgrade.cancel');
});

// Maya's server posts here. No session, no CSRF token, no auth - see the
// exemption in bootstrap/app.php and the reasoning in PaymentController.
Route::post('/maya/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:60,1')->name('maya.webhook');

/*
   The map itself is open to everyone - a guest can browse Metro Cebu, search,
   and see where the churches are. A church's own page is where the schedules,
   reviews and visit history live, so that needs an account.
*/
Route::get('/map', [MapController::class, 'index'])->name('map');

// Search engines: which public pages to list, and what to skip.
Route::get('/sitemap.xml', function () {
    $pages = ['root' => 'weekly', 'map' => 'weekly', 'register' => 'monthly', 'login' => 'monthly', 'privacy' => 'yearly', 'terms' => 'yearly', 'about' => 'yearly'];

    return response()->view('sitemap', ['pages' => $pages])->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow: /admin\nDisallow: /profile\nDisallow: /chatbot\nDisallow: /plan\nDisallow: /p/\n\nSitemap: ".route('sitemap')."\n"
)->header('Content-Type', 'text/plain'))->name('robots');

// Confirm your email (only asked when GIYA_VERIFY_EMAIL=true).
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:12,1'])->whereNumber('id')->name('verification.verify');
Route::get('/email/check', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'pending'])->name('verification.pending');
Route::post('/email/check/resend', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resendPending'])
    ->middleware('throttle:3,10')->name('verification.resend-pending');
Route::post('/email/check/code', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'codePending'])
    ->middleware('throttle:10,10')->name('verification.code-pending');
// "Send feedback": about GIYA itself, not a church.
Route::middleware('auth')->group(function () {
    Route::get('/feedback', [\App\Http\Controllers\AppFeedbackController::class, 'create'])->name('app-feedback');
    Route::post('/feedback', [\App\Http\Controllers\AppFeedbackController::class, 'store'])->middleware('throttle:5,10')->name('app-feedback.store');
});

// Checking in at a church from the map (VisitController).
Route::middleware('auth')->group(function () {
    Route::post('/visits/arrive', [\App\Http\Controllers\VisitController::class, 'arrive'])->middleware('throttle:30,1')->name('visits.arrive');
    Route::post('/visits/{visit}/undo', [\App\Http\Controllers\VisitController::class, 'undo'])->middleware('throttle:30,1')->name('visits.undo');
});

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,10')->name('verification.send');
    Route::post('/email/verify/code', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'code'])
        ->middleware('throttle:10,10')->name('verification.code');
});

// A shared itinerary: anyone with the link can see it; signed in, save a copy.
Route::get('/p/{token}', [\App\Http\Controllers\SharedItineraryController::class, 'show'])->middleware('throttle:giya-actions')->name('shared.show');
Route::post('/p/{token}/copy', [\App\Http\Controllers\SharedItineraryController::class, 'copy'])
    ->middleware(['auth', 'confirmed.email', 'throttle:10,1'])->name('shared.copy');

// Shown by the offline worker (public/sw.js) when there is no signal.
Route::view('/offline', 'offline')->name('offline');

// Privacy Policy and Terms: open to everyone, linked from sign-up and the footer.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/about', 'about')->name('about');

Route::middleware('auth')->group(function () {
    Route::get('/churches/{church}', [MapController::class, 'show'])->name('churches.show');
    Route::post('/churches/{church}/notify', [MapController::class, 'toggleNotify'])->middleware('throttle:giya-actions')->name('churches.notify');
});

/* --------------------------------------------------------------- Admin */
Route::middleware(['auth', 'admin', 'admin.log'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/insights',  [\App\Http\Controllers\Admin\InsightsController::class, 'insights'])->name('insights');
    Route::get('/users',     [AdminUserController::class, 'index'])->name('users');
    Route::get('/search',    \App\Http\Controllers\Admin\SearchController::class)->name('search');
    Route::get('/users/export',      [AdminUserController::class, 'export'])->name('users.export');
    Route::patch('/users/{user}',    [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/confirm-email', [AdminUserController::class, 'confirmEmail'])->name('users.confirm');
    Route::delete('/users/{user}',   [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/destinations',                  [AdminChurchController::class, 'index'])->name('destinations');
    Route::post('/destinations',                 [AdminChurchController::class, 'store'])->name('destinations.store');
    Route::post('/destinations/import',          [AdminChurchController::class, 'import'])->name('destinations.import');
    Route::patch('/destinations/{church}/toggle',[AdminChurchController::class, 'toggle'])->name('destinations.toggle');
    Route::post('/destinations/{church}/photo', [AdminChurchController::class, 'updatePhoto'])->name('destinations.photo');
    Route::get('/destinations/{church}/panel',   [AdminChurchController::class, 'panel'])->name('destinations.panel');
    Route::patch('/destinations/{church}/move',  [AdminChurchController::class, 'move'])->name('destinations.move');
    Route::delete('/destinations/{church}',      [AdminChurchController::class, 'destroy'])->name('destinations.destroy');

    Route::get('/schedules',              [AdminScheduleController::class, 'index'])->name('schedules');
    Route::post('/schedules',             [AdminScheduleController::class, 'store'])->name('schedules.store');
    Route::get('/schedules/export',       [AdminScheduleController::class, 'export'])->name('schedules.export');
    Route::post('/schedules/copy',        [AdminScheduleController::class, 'copy'])->name('schedules.copy');
    Route::post('/schedules/bulk',        [AdminScheduleController::class, 'bulk'])->name('schedules.bulk');
    Route::post('/schedules/{schedule}/days', [AdminScheduleController::class, 'toggleDay'])->name('schedules.days');
    Route::delete('/schedules/{schedule}',[AdminScheduleController::class, 'destroy'])->name('schedules.destroy');

    Route::get('/feedback',              [AdminFeedbackController::class, 'index'])->name('feedback');
    Route::patch('/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');
    Route::get('/app-feedback', [\App\Http\Controllers\Admin\AppFeedbackController::class, 'index'])->name('app-feedback');
    Route::get('/app-feedback/download', [\App\Http\Controllers\Admin\AppFeedbackController::class, 'download'])->name('app-feedback.download');

    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions');
    Route::post('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('plans.store');
    Route::put('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('plans.update');
    Route::patch('/plans/{plan}/toggle', [\App\Http\Controllers\Admin\PlanController::class, 'toggle'])->name('plans.toggle');
    Route::delete('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('plans.destroy');
    
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
    Route::get('/reports/download/{report}', [AdminReportController::class, 'download'])->name('reports.download');

    // System: maintenance mode.
    Route::get('/system', [\App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system');
    Route::get('/system/preview', [\App\Http\Controllers\Admin\SystemController::class, 'preview'])->name('system.preview');
    Route::patch('/system/maintenance', [\App\Http\Controllers\Admin\SystemController::class, 'maintenance'])->name('system.maintenance');

    // System: announcements to every pilgrim.
    Route::get('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'index'])->name('announcements');
    Route::post('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('/announcements/{announcement}/stop', [\App\Http\Controllers\Admin\AnnouncementController::class, 'stop'])->name('announcements.stop');
    Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // System: what each admin changed (written by the admin.log middleware above).
    Route::get('/activity', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/',                    [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
    Route::patch('/{notification}',    [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
    Route::delete('/{notification}',   [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
    Route::patch('/',                  [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('readAll');
    Route::delete('/',                 [\App\Http\Controllers\NotificationController::class, 'clear'])->name('clear');
});

// A page left open for hours gets a fresh form token here (giya-session.js).
Route::get('/session/refresh', [\App\Http\Controllers\SessionController::class, 'refresh'])->name('session.refresh');
Route::post('/admin/keep-alive', [\App\Http\Controllers\SessionController::class, 'keepAlive'])
    ->middleware(['auth', 'admin'])->name('admin.keep-alive');
Route::post('/admin/idle-sign-out', [\App\Http\Controllers\SessionController::class, 'idleSignOut'])->name('admin.idle-sign-out');
Route::get('/admin/idle-sign-out', [\App\Http\Controllers\SessionController::class, 'idleSignedOut'])->name('admin.idle-signed-out');

// Phone notifications (Web Push): the browser's subscription.
Route::middleware(['auth', 'throttle:20,1'])->prefix('push')->name('push.')->group(function () {
    Route::post('/subscribe',   [\App\Http\Controllers\PushController::class, 'subscribe'])->name('subscribe');
    Route::post('/unsubscribe', [\App\Http\Controllers\PushController::class, 'unsubscribe'])->name('unsubscribe');
});
