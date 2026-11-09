<?php

use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
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
    Route::prefix('plan')->name('plan.')->controller(ItineraryController::class)->group(function () {
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
        Route::get('/{itinerary}',     'show')->name('show');
        Route::post('/{itinerary}/ratings', 'storeRatings')->middleware('confirmed.email')->name('ratings');
        Route::delete('/{itinerary}',  'destroy')->name('destroy');
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
    $pages = ['root' => 'weekly', 'map' => 'weekly', 'register' => 'monthly', 'login' => 'monthly', 'privacy' => 'yearly', 'terms' => 'yearly'];

    return response()->view('sitemap', ['pages' => $pages])->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow: /admin\nDisallow: /profile\nDisallow: /chatbot\nDisallow: /plan\n\nSitemap: ".route('sitemap')."\n"
)->header('Content-Type', 'text/plain'))->name('robots');

// Confirm your email (only asked when GIYA_VERIFY_EMAIL=true).
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:12,1'])->whereNumber('id')->name('verification.verify');
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,10')->name('verification.send');
});

// Shown by the offline worker (public/sw.js) when there is no signal.
Route::view('/offline', 'offline')->name('offline');

// Privacy Policy and Terms: open to everyone, linked from sign-up and the footer.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

Route::middleware('auth')->group(function () {
    Route::get('/churches/{church}', [MapController::class, 'show'])->name('churches.show');
    Route::post('/churches/{church}/notify', [MapController::class, 'toggleNotify'])->middleware('throttle:giya-actions')->name('churches.notify');
});

/* --------------------------------------------------------------- Admin */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/insights',  [\App\Http\Controllers\Admin\InsightsController::class, 'insights'])->name('insights');
    Route::get('/content-gaps', [\App\Http\Controllers\Admin\InsightsController::class, 'gaps'])->name('gaps');
    Route::get('/users',     [AdminUserController::class, 'index'])->name('users');
    Route::get('/users/export',      [AdminUserController::class, 'export'])->name('users.export');
    Route::patch('/users/{user}',    [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}',   [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/destinations',                  [AdminChurchController::class, 'index'])->name('destinations');
    Route::post('/destinations',                 [AdminChurchController::class, 'store'])->name('destinations.store');
    Route::post('/destinations/import',          [AdminChurchController::class, 'import'])->name('destinations.import');
    Route::patch('/destinations/{church}/toggle',[AdminChurchController::class, 'toggle'])->name('destinations.toggle');
    Route::post('/destinations/{church}/photo', [AdminChurchController::class, 'updatePhoto'])->name('destinations.photo');
    Route::delete('/destinations/{church}',      [AdminChurchController::class, 'destroy'])->name('destinations.destroy');

    Route::get('/schedules',              [AdminScheduleController::class, 'index'])->name('schedules');
    Route::post('/schedules',             [AdminScheduleController::class, 'store'])->name('schedules.store');
    Route::get('/schedules/export',       [AdminScheduleController::class, 'export'])->name('schedules.export');
    Route::delete('/schedules/{schedule}',[AdminScheduleController::class, 'destroy'])->name('schedules.destroy');

    Route::get('/feedback',              [AdminFeedbackController::class, 'index'])->name('feedback');
    Route::patch('/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');

    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions');
    
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
    Route::get('/reports/download/{report}', [AdminReportController::class, 'download'])->name('reports.download');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/',                    [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
    Route::patch('/{notification}',    [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
    Route::delete('/{notification}',   [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
    Route::patch('/',                  [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('readAll');
    Route::delete('/',                 [\App\Http\Controllers\NotificationController::class, 'clear'])->name('clear');
});
