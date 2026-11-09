<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends GIYA's email through Brevo's web API (MAIL_MAILER=brevo).
 *
 * Render's free plan blocks the ports email servers use (SMTP), so Gmail
 * SMTP cannot send from there. Brevo is reached over ordinary HTTPS, so it
 * works on Render and Vercel alike. Free plan: 300 emails a day, sent from
 * an address verified in Brevo (giya.support@gmail.com).
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? new Address((string) config('mail.from.address'), (string) config('mail.from.name'));
        $people = fn (array $list) => array_map(fn (Address $a) => array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]), $list);

        $payload = array_filter([
            'sender'      => array_filter(['email' => $from->getAddress(), 'name' => $from->getName() ?: null]),
            'to'          => $people($email->getTo()),
            'cc'          => $people($email->getCc()) ?: null,
            'bcc'         => $people($email->getBcc()) ?: null,
            'replyTo'     => ($r = $email->getReplyTo()[0] ?? null) ? ['email' => $r->getAddress()] : null,
            'subject'     => (string) $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ? (string) $email->getHtmlBody() : null,
            'textContent' => $email->getTextBody() ? (string) $email->getTextBody() : null,
        ]);

        Http::withHeaders(['api-key' => $this->key, 'accept' => 'application/json'])
            ->timeout(10)
            ->post('https://api.brevo.com/v3/smtp/email', $payload)
            ->throw();
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
