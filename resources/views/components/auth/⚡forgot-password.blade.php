<?php

use App\Actions\Auth\PasswordResetAction;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public ?string $successMessage = null;
    public ?string $warningMessage = null;

    public function sendLink(): void
    {
        $this->reset(['successMessage', 'warningMessage']);
        $this->validate(['email' => ['required', 'email', 'exists:devotees,email']], [
            'email.exists' => 'No GIYA account is registered with that email address.',
        ]);
        try {
            app(PasswordResetAction::class)->sendLink($this->email);
        } catch (\Throwable $exception) {
            $this->warningMessage = 'Mail could not be sent. Your reset link was written to storage/logs/laravel.log.';
            return;
        }
        $this->successMessage = "A reset link has been sent to {$this->email}. It expires in ".PasswordResetAction::TOKEN_TTL_MINUTES." minutes.";
    }
};
?>

<div>
    @if ($successMessage)<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><span>{{ $successMessage }}</span></div>@endif
    @if ($warningMessage)<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $warningMessage }}</span></div>@endif
    @error('email')<div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $message }}</span></div>@enderror
    <p style="font-size: 0.8125rem;color:var(--text-muted);line-height:1.7;margin-bottom:20px">Enter the email address linked to your GIYA account and we will send you a link to set a new password. The link is valid for 60 minutes.</p>
    <form wire:submit="sendLink">
        <div class="field"><label class="form-label" for="forgot-email">Email Address</label><input id="forgot-email" type="email" wire:model="email" class="giya-input @error('email') is-invalid @enderror" placeholder="juan@email.com" required autofocus autocomplete="email"></div>
        <button type="submit" class="btn btn-primary btn-w-full" wire:loading.attr="disabled" wire:target="sendLink"><span wire:loading.remove wire:target="sendLink">Send Reset Link</span><span wire:loading wire:target="sendLink">Sending...</span></button>
    </form>
    <p class="auth-footer"><a href="{{ route('login') }}" wire:navigate style="color:var(--text-muted);font-weight:400">Back to sign in</a></p>
</div>



