<?php
namespace Core\Adapter\Out\Persistence\Model;
use Hyperf\Database\Model\Model;

class AccountModel extends Model {
    protected ?string $table = 'account';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    public bool $timestamps = false;
    protected array $fillable = ['id','name','balance'];
}