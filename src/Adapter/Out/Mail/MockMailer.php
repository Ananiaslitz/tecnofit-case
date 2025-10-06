<?php
namespace Core\Adapter\Out\Mail;

use Core\Adapter\Out\Observability\RichEvent;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Domain\Port\MailerPort;
use Core\Domain\ValueObject\Money;
use Hyperf\Logger\LoggerFactory;

final class MockMailer implements MailerPort
{
    public function __construct(
        private LoggerFactory $loggerFactory,
        private RichEventEmitter $events,
    ) {}

    public function sendWithdrawEmail(string $to, string $pixType, string $pixKey, Money $amount, \DateTimeInterface $when): void
    {
        $this->events->emit(new RichEvent(
            name: 'email.sent.mock',
            version: '1.0',
            level: 'info',
            message: 'Mock email sent',
            attrs: [
                'email.to' => $to,
                'withdraw.amount_cents' => (int) round($amount->amountInCents * 100),
                'pix.type' => $pixType,
                'pix.key_hash' => substr(sha1($pixKey), 0, 16),
                'sent_at' => $when->format('Y-m-d H:i:s'),
            ],
            meta: ['component' => 'adapter', 'adapter' => 'MockMailer']
        ));

        $this->loggerFactory->get('mail')->info('MOCK email', [
            'to' => $to,
            'subject' => 'Saque PIX efetuado',
            'body_preview' => sprintf(
                "Data/Hora: %s | Valor: R$ %.2f | PIX: %s (%s)",
                $when->format('Y-m-d H:i:s'),
                $amount->amountInCents,
                $pixKey,
                $pixType
            ),
        ]);
    }
}
