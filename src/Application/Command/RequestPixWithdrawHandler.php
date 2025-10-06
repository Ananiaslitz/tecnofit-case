<?php

namespace Core\Application\Command;

use Core\Adapter\Out\Observability\RichEvent;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\Clock;
use Core\Domain\Port\IdGenerator;
use Core\Domain\Port\MailerPort;
use Core\Domain\Port\TxManager;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Service\WithdrawalDomainService;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use Core\Domain\ValueObject\Schedule;
use InvalidArgumentException;

class RequestPixWithdrawHandler
{
    public function __construct(
        private WithdrawalDomainService $domain,
        private AccountRepository $accounts,
        private WithdrawRepository $withdraws,
        private MailerPort $mailer,
        private TxManager $tx,
        private IdGenerator $ids,
        private Clock $clock,
        private RichEventEmitter $events,
    ) {}

    /**
     * @throws \DateMalformedStringException
     */
    public function __invoke(RequestPixWithdrawCommand $cmd): array
    {
        if (strtoupper($cmd->method) !== 'PIX') {
            throw new InvalidArgumentException('Only PIX withdrawals are supported.');
        }
        if (strtolower($cmd->pixType) !== 'email') {
            throw new InvalidArgumentException('Only PIX type "email" is supported for this case.');
        }
        if ($cmd->amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $pix = new PixKey($cmd->pixType, $cmd->pixKey);
        $money = Money::fromDecimal($cmd->amount);

        $tz = method_exists($this->clock, 'timezone')
            ? $this->clock->timezone()
            : new \DateTimeZone(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

        $when = null;
        if (!empty($cmd->schedule)) {
            $when = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $cmd->schedule, $tz);
            if ($when === false) {
                throw new \InvalidArgumentException('Invalid schedule format, expected Y-m-d H:i');
            }
        }

        $now = $this->clock->now();
        if ($when) {
            if ($when <= $now) {
                throw new InvalidArgumentException('Schedule cannot be in the past.');
            }
            if ($when > $now->modify('+7 days')) {
                throw new InvalidArgumentException('Schedule cannot be more than 7 days in the future.');
            }
        }

        $schedule = new Schedule($when, $this->clock);

        $wid = $this->tx->transactional(function () use ($pix, $money, $schedule, $cmd) {
            return $this->domain->request(
                $this->accounts,
                $this->withdraws,
                $this->ids,
                $this->clock,
                $cmd->accountId,
                $money,
                $pix,
                $schedule
            );
        });

        $this->events->emit(new RichEvent(
            name: 'withdraw.requested',
            version: '1.0',
            level: 'info',
            message: 'Withdraw requested',
            attrs: [
                'account.id'              => $cmd->accountId,
                'withdraw.id'             => $wid,
                'withdraw.amount_cents'   => (int) round($money->amount() * 100),
                'withdraw.scheduled'      => $schedule->isScheduled(),
                'withdraw.scheduled_for'  => $when?->format('Y-m-d H:i'),
                'method'                  => 'PIX',
                'pix.type'                => $pix->type(),
                'pix.key_hash'            => substr(sha1($pix->key()), 0, 16),
            ],
            meta: ['component' => 'command', 'command' => 'RequestPixWithdraw']
        ));

        $w = $this->withdraws->byId($wid);
        if ($w && $w->done) {
            $outcome = $w->error ? 'failed' : 'success';
            $this->events->emit(new RichEvent(
                'withdraw.processed',
                '1.0',
                $w->error ? 'warning' : 'info',
                "Withdraw processed ($outcome)",
                attrs: [
                    'account.id'            => $w->accountId,
                    'withdraw.id'           => $w->id,
                    'withdraw.amount_cents' => (int) $w->amount->amount(),
                    'outcome'               => $outcome,
                    'error_reason'          => $w->errorReason,
                ],
                meta: ['component' => 'domain', 'operation' => 'process_immediate']
            ));

            if (! $w->error) {
                $this->mailer->sendWithdrawEmail(
                    $w->pix->key(),
                    $w->pix->type(),
                    $w->pix->key(),
                    $w->amount,
                    $this->clock->now()
                );
            }
        }

        return ['withdraw_id' => $wid];
    }
}
