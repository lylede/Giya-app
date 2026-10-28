<?php

use App\Actions\Auth\PasswordResetAction;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

new class extends Component
{
    public string $token;
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token, string $email = ''): void
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function resetPassword(): void
    {
        $data = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['password.confirmed' => 'Passwords do not match.']);

        // One implementation, shared with PasswordResetController. This
        // component used to carry its own copy, including the expiry bug.
        try {
            app(PasswordResetAction::class)->reset($this->token, $data['email'], $data['password']);
        } catch (\RuntimeException $exception) {
            $this->addError('token', $exception->getMessage());
            return;
        }

        session()->flash('success', 'Password reset successfully. Please sign in.');
        $this->redirect(route('login'), navigate: true);
    }
};
?>

<div>
    @error('token')<div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>@enderror
    @error('email')<div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>@enderror
    <form wire:submit="resetPassword">
        <div class="field"><label class="form-label" for="reset-email">{{ __('giya.profile.email') }}</label><input id="reset-email" type="email" wire:model="email" class="giya-input" value="{{ $email }}" disabled></div>
        <div class="field"><label class="form-label" for="reset-password">{{ __('giya.auth.new_pw') }}</label><div class="input-wrap"><input id="reset-password" type="password" wire:model="password" class="giya-input @error('password') is-invalid @enderror" placeholder="{{ __('giya.auth.pw_min') }}" required minlength="8" autofocus autocomplete="new-password"><button type="button" class="input-suffix" onclick="giyaTogglePassword('reset-password', this)" aria-label="{{ __('giya.common.show_pw') }}"><i class="bi bi-eye"></i></button></div>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="form-label" for="reset-password-confirmation">{{ __('giya.auth.confirm_new_pw') }}</label><div class="input-wrap"><input id="reset-password-confirmation" type="password" wire:model="password_confirmation" class="giya-input" placeholder="{{ __('giya.auth.new_pw_again') }}" required minlength="8" autocomplete="new-password" oninput="this.setCustomValidity(this.value !== document.getElementById('reset-password').value ? 'Passwords do not match.' : '')"><button type="button" class="input-suffix" onclick="giyaTogglePassword('reset-password-confirmation', this)" aria-label="{{ __('giya.common.show_pw') }}"><i class="bi bi-eye"></i></button></div></div>
        <button type="submit" class="btn btn-primary btn-w-full" wire:loading.attr="disabled" wire:target="resetPassword"><span wire:loading.remove wire:target="resetPassword">{{ __('giya.auth.reset_pw') }}</span><span wire:loading wire:target="resetPassword">{{ __('giya.auth.resetting') }}</span></button>
    </form>
</div>
