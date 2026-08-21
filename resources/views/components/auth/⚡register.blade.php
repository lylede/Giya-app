<?php

use App\Actions\Auth\RegisterAction;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:devotees,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'name.required' => 'Full name is required.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $user = app(RegisterAction::class)->execute($data, request());
        session()->flash('success', "Welcome to GIYA, {$user->firstName()}!");
        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div>
    <div class="auth-tabs">
        <a href="{{ route('login') }}" class="auth-tab" wire:navigate>Sign In</a>
        <a href="{{ route('register') }}" class="auth-tab active" wire:navigate>Sign Up</a>
    </div>
    <form wire:submit="register">
        <div class="field"><label class="form-label" for="register-name">Full Name</label><input id="register-name" type="text" wire:model="name" class="giya-input @error('name') is-invalid @enderror" placeholder="Juan dela Cruz" required autofocus autocomplete="name">@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="form-label" for="register-email">Email Address</label><input id="register-email" type="email" wire:model="email" class="giya-input @error('email') is-invalid @enderror" placeholder="juan@email.com" required autocomplete="email">@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="form-label" for="register-password">Password</label><div class="input-wrap"><input id="register-password" type="password" wire:model="password" class="giya-input @error('password') is-invalid @enderror" placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password"><button type="button" class="input-suffix" onclick="giyaTogglePassword('register-password', this)" aria-label="Show password"><i class="bi bi-eye"></i></button></div>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="form-label" for="register-password-confirmation">Confirm Password</label><div class="input-wrap"><input id="register-password-confirmation" type="password" wire:model="password_confirmation" class="giya-input" placeholder="Re-enter your password" required minlength="8" autocomplete="new-password" oninput="this.setCustomValidity(this.value !== document.getElementById('register-password').value ? 'Passwords do not match.' : '')"><button type="button" class="input-suffix" onclick="giyaTogglePassword('register-password-confirmation', this)" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
        <button type="submit" class="btn btn-primary btn-w-full" wire:loading.attr="disabled" wire:target="register"><span wire:loading.remove wire:target="register">Create Account</span><span wire:loading wire:target="register">Creating account...</span></button>
    </form>
    <p class="auth-footer">Already have an account? <a href="{{ route('login') }}" wire:navigate>Sign in</a></p>
</div>