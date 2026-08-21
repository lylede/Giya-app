<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            'email' => ['required', 'email', 'exists:devotees,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['password.confirmed' => 'Passwords do not match.']);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (! $record || ! Hash::check($this->token, $record->token)) {
            $this->addError('token', 'This reset link is invalid. Please request a new one.');
            return;
        }
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            $this->addError('token', 'This reset link has expired. Please request a new one.');
            return;
        }

        User::where('email', $data['email'])->update([
            'password_hash' => Hash::make($data['password']),
            'updated_at' => now(),
        ]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        session()->flash('success', 'Password reset successfully. Please sign in.');
        $this->redirect(route('login'), navigate: true);
    }
};
?>

<div>
    @error('token')<div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>@enderror
    @error('email')<div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>@enderror
    <form wire:submit="resetPassword">
        <div class="field"><label class="form-label" for="reset-email">Email Address</label><input id="reset-email" type="email" wire:model="email" class="giya-input" value="{{ $email }}" disabled></div>
        <div class="field"><label class="form-label" for="reset-password">New Password</label><div class="input-wrap"><input id="reset-password" type="password" wire:model="password" class="giya-input @error('password') is-invalid @enderror" placeholder="Minimum 8 characters" required minlength="8" autofocus autocomplete="new-password"><button type="button" class="input-suffix" onclick="giyaTogglePassword('reset-password', this)" aria-label="Show password"><i class="bi bi-eye"></i></button></div>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="form-label" for="reset-password-confirmation">Confirm New Password</label><div class="input-wrap"><input id="reset-password-confirmation" type="password" wire:model="password_confirmation" class="giya-input" placeholder="Re-enter new password" required minlength="8" autocomplete="new-password" oninput="this.setCustomValidity(this.value !== document.getElementById('reset-password').value ? 'Passwords do not match.' : '')"><button type="button" class="input-suffix" onclick="giyaTogglePassword('reset-password-confirmation', this)" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
        <button type="submit" class="btn btn-primary btn-w-full" wire:loading.attr="disabled" wire:target="resetPassword"><span wire:loading.remove wire:target="resetPassword">Reset Password</span><span wire:loading wire:target="resetPassword">Resetting...</span></button>
    </form>
</div>