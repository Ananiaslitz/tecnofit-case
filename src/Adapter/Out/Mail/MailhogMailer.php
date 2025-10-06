<?php
namespace Core\Adapter\Out\Mail;

use Core\Domain\Port\MailerPort;
use Core\Domain\ValueObject\Money;
use FriendsOfHyperf\Mail\MailManager;

final class MailhogMailer implements MailerPort
{
    public function __construct(private MailManager $mail) {}

    public function sendWithdrawEmail(
        string $to,
        string $pixType,
        string $pixKey,
        Money $amount,
        \DateTimeInterface $when
    ): void {
        $subject = 'Saque PIX efetuado';
        $body = sprintf(
            "Data/Hora: %s\nValor: R$ %.2f\nPIX: %s (%s)\n",
            $when->format('Y-m-d H:i:s'),
            $amount->amountInCents,
            $pixKey,
            $pixType
        );

        $this->mail->mailer(getenv('MAIL_MAILER', 'smtp'))->raw($body, function ($msg) use ($to, $subject) {
            $msg->to($to)->subject($subject);
        });

    }
}
