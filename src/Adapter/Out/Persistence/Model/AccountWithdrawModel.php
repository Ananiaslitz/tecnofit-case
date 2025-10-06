<?php

namespace Core\Adapter\Out\Persistence\Model;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\HasOne;

class AccountWithdrawModel extends Model
{
    protected ?string $table = 'account_withdraw';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    public bool $timestamps = false;
    protected array $casts = [
        'amount'        => 'decimal:2',
        'amount_cents'  => 'integer',
        'scheduled'     => 'boolean',
        'done'          => 'boolean',
        'error'         => 'boolean',
        'scheduled_for' => 'datetime',
        'processed_at'  => 'datetime',
    ];

    protected array $fillable = [
        'id', 'account_id', 'method', 'amount', 'amount_cents', 'scheduled', 'scheduled_for', 'done', 'error', 'error_reason', 'created_at'
    ];

    public function pix(): HasOne
    {
        return $this->hasOne(AccountWithdrawPixModel::class, 'account_withdraw_id', 'id');
    }
}
