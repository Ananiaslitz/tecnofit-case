<?php
namespace Core\Adapter\Out\Persistence\Model;
use Hyperf\Database\Model\Model;

class AccountWithdrawPixModel extends Model {
    protected ?string $table = 'account_withdraw_pix';
    protected string $primaryKey = 'account_withdraw_id';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    public bool $timestamps = true;

    protected array $fillable = ['account_withdraw_id','type','key','created_at','updated_at'];

    public function withdraw()
    {
        return $this->belongsTo(AccountWithdrawModel::class, 'account_withdraw_id', 'id');
    }
}
