<?php

use App\Actions\Auth\LoginAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        $user = app(LoginAction::class)->execute($credentials, $this->remember, request());

        $message = 'Welcome back, '.$user->firstName().'!';
        $route = $user->isAdmin() ? route('admin.dashboard') : route('home');

        $this->redirect($route, navigate: true);
        session()->flash('success', $message);
    }
};
?>

<div>
    <div class="auth-tabs">
        <a href="{{ route('login') }}" class="auth-tab active" wire:navigate>Sign In</a>
        <a href="{{ route('register') }}" class="auth-tab" wire:navigate>Sign Up</a>
    </div>

    @error('email')
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>
    @enderror

    <form wire:submit="login" novalidate>
        <div class="field">
            <label class="form-label" for="login-email">Email Address</label>
            <input id="login-email" type="email" wire:model="email" class="giya-input @error('email') is-invalid @enderror"
                   placeholder="juan@email.com" required autofocus autocomplete="email" aria-describedby="login-email-error">
            @error('email')<span id="login-email-error" class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="form-label" for="login-password">Password</label>
            <div class="input-wrap">
                <input id="login-password" type="password" wire:model="password" class="giya-input @error('password') is-invalid @enderror"
                       placeholder="********" required autocomplete="current-password" aria-describedby="login-password-error">
                <button type="button" class="input-suffix" onclick="giyaTogglePassword('login-password', this)" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')<span id="login-password-error" class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <label class="d-flex align-items-center gap-2 m-0" style="font-size: 0.8125rem;color:var(--text-muted);cursor:pointer">
                <input type="checkbox" wire:model="remember"> Remember me
            </label>
            <a href="{{ route('password.request') }}" wire:navigate style="font-size: 0.8125rem;color:var(--primary);font-weight:600">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Sign In</span>
            <span wire:loading wire:target="login">Signing in...</span>
        </button>
    </form>

    <p class="auth-footer">No account yet? <a href="{{ route('register') }}" wire:navigate>Create one</a></p>
</div>
