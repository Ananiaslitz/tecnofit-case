<?php

use Core\Domain\Port\{AccountRepository,
    WithdrawReadRepository,
    WithdrawRepository,
    MailerPort,
    IdGenerator,
    Clock,
    TxManager};
use Core\Adapter\Out\Http\WithdrawController;
use Core\Shared\Http\IdempotencyService;

use Core\Adapter\Out\Mail\MockMailer;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Shared\Http\Middleware\HttpServerTracingMiddleware;
use Core\Shared\Observability\RequestIdService;
use Core\Shared\Observability\RequestIdServiceInterface;
use Core\Shared\Observability\TraceContextService;
use Core\Shared\Observability\TraceContextServiceInterface;
use Core\Shared\Sanitization\PixMasker;
use Core\Shared\Sanitization\PixMaskerInterface;
use Core\Shared\Transaction\HyperfTxManager;
use Core\Adapter\Out\Persistence\{MySqlAccountRepository, MySqlWithdrawReadRepository, MySqlWithdrawRepository};
use Core\Adapter\Out\Mail\MailhogMailer;
use Core\Adapter\Out\Time\SystemClock;
use Core\Adapter\Out\Id\RamseyUuidGenerator;

return [
    AccountRepository::class => MySqlAccountRepository::class,
    WithdrawController::class => WithdrawController::class,
    WithdrawRepository::class => MySqlWithdrawRepository::class,
    WithdrawReadRepository::class=> MySqlWithdrawReadRepository::class,
    MailerPort::class => getenv('MAIL_MOCK', true) ? MockMailer::class : MailhogMailer::class,
    IdGenerator::class       => RamseyUuidGenerator::class,
    Clock::class             => SystemClock::class,
    TxManager::class         => HyperfTxManager::class,
    IdempotencyService::class => IdempotencyService::class,

    RichEventEmitter::class  => RichEventEmitter::class,

    HttpServerTracingMiddleware::class => HttpServerTracingMiddleware::class,

    TraceContextServiceInterface::class => TraceContextService::class,
    RequestIdServiceInterface::class => RequestIdService::class,
    PixMaskerInterface::class => PixMasker::class,
];
