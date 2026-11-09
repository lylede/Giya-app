<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Admin > Email check: which mail service GIYA is using, and a test email
 * to the admin that shows the exact error when sending fails. Settings are
 * shown as "set / not set", never their values.
 */
class MailCheckController extends Controller
{
    public function index(): View
    {
        $mailer = (string) config('mail.default');
        $smtp = config('mail.mailers.smtp');

        return view('admin.mail-check', [
            'mailer'   => $mailer,
            'from'     => (string) config('mail.from.address'),
            'fromName' => (string) config('mail.from.name'),
            'verify'   => (bool) config('app.verify_email'),
            'rows'     => array_filter([
                'Mail service (MAIL_MAILER)' => $mailer ?: 'not set',
                'SMTP host'                  => $mailer === 'smtp' ? ($smtp['host'] ?? 'not set').':'.($smtp['port'] ?? '') : null,
                'SMTP username'              => $mailer === 'smtp' ? (($smtp['username'] ?? '') !== '' ? 'set' : 'not set') : null,
                'SMTP password'              => $mailer === 'smtp' ? (($smtp['password'] ?? '') !== '' ? 'set' : 'not set') : null,
                'Brevo API key'              => $mailer === 'brevo' ? (config('services.brevo.key') ? 'set' : 'not set') : null,
                'From address'               => (string) config('mail.from.address') ?: 'not set',
                'Confirm-email step'         => config('app.verify_email') ? 'on' : 'off',
            ]),
            'hint'     => $this->hint($mailer),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $to = $request->user()->email;
        $started = microtime(true);

        try {
            Mail::raw("This is a test email from GIYA's admin Email check.\n\nIf you can read this, email works: sign-up confirmation and password reset emails will arrive.",
                fn ($m) => $m->to($to)->subject('GIYA - Email check'));

            return back()->with('success', 'Sent to '.$to.' in '.round(microtime(true) - $started, 1).'s. Check that inbox (and Spam).');
        } catch (\Throwable $e) {
            return back()->with('mail_error', [
                'type'    => class_basename($e),
                'message' => $this->clean($e->getMessage()),
                'seconds' => round(microtime(true) - $started, 1),
            ]);
        }
    }

    /** The likely fix for the mail service in use. */
    protected function hint(string $mailer): string
    {
        return match ($mailer) {
            'log', 'array', '' => 'Emails are only written to the log, never sent. Set MAIL_MAILER=brevo (or smtp) to send real email.',
            'smtp'  => 'Render\'s free plan blocks SMTP, so Gmail SMTP fails there (it works on Vercel). On Render use MAIL_MAILER=brevo with a BREVO_API_KEY.',
            'brevo' => 'Brevo sends over HTTPS, which works on Render and Vercel. The From address must be a verified sender in Brevo.',
            default => '',
        };
    }

    /** Error text without anything that looks like a key or password. */
    protected function clean(string $text): string
    {
        $text = preg_replace('/(xkeysib-|api-key[":= ]+)[A-Za-z0-9-]+/i', '$1[hidden]', $text);

        return mb_substr((string) $text, 0, 500);
    }
}
