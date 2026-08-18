<?php

use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GIYA — Web Routes
|--------------------------------------------------------------------------
| Every internal link in the Blade templates resolves through a named route
| below. No route points at an external service.
*/

Route::redirect('/', '/login')->name('root');

/* ---------------------------------------------------------------- Guest */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/register',  [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/forgot-password',  [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password',        [PasswordResetController::class, 'reset'])->name('password.update');
});

/* ------------------------------------------------------- Authenticated */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/home',    [HomeController::class, 'index'])->name('home');
    Route::get('/map',     [MapController::class, 'index'])->name('map');
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot');

    /* Profile */
    Route::get('/profile',            [ProfileController::class, 'index'])->name('profile');
    Route::patch('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('/favorites/toggle',     [ProfileController::class, 'toggleFavorite'])->name('favorites.toggle');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/review', [ProfileController::class, 'storeReview'])->name('profile.review');

    /* Plan / Itineraries — static segments registered before {itinerary} */
    Route::prefix('plan')->name('plan.')->controller(ItineraryController::class)->group(function () {
        Route::get('/',                'hub')->name('hub');
        Route::get('/create',          'create')->name('create');
        Route::get('/visita-iglesia',  'visita')->name('visita');
        Route::get('/my-itineraries',  'index')->name('index');
        Route::post('/',               'store')->name('store');
        Route::post('/stop/visited',   'markVisited')->name('stop.visited');
        Route::get('/{itinerary}',     'show')->name('show');
        Route::delete('/{itinerary}',  'destroy')->name('destroy');
    });
});

/* --------------------------------------------------------------- Admin */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users',     [AdminUserController::class, 'index'])->name('users');

    Route::get('/destinations',                  [AdminChurchController::class, 'index'])->name('destinations');
    Route::post('/destinations',                 [AdminChurchController::class, 'store'])->name('destinations.store');
    Route::patch('/destinations/{church}/toggle',[AdminChurchController::class, 'toggle'])->name('destinations.toggle');
    Route::post('/destinations/{church}/photo', [AdminChurchController::class, 'updatePhoto'])->name('destinations.photo');
    Route::delete('/destinations/{church}',      [AdminChurchController::class, 'destroy'])->name('destinations.destroy');

    Route::get('/schedules',              [AdminScheduleController::class, 'index'])->name('schedules');
    Route::post('/schedules',             [AdminScheduleController::class, 'store'])->name('schedules.store');
    Route::delete('/schedules/{schedule}',[AdminScheduleController::class, 'destroy'])->name('schedules.destroy');

    Route::get('/feedback',              [AdminFeedbackController::class, 'index'])->name('feedback');
    Route::patch('/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');

    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions');
});
