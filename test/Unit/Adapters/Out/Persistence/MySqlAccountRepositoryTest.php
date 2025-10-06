<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountModel;
use Core\Adapter\Out\Persistence\MySqlAccountRepository;
use Core\Domain\Entity\Account;
use Core\Domain\ValueObject\Money;
use Core\Shared\Exception\BusinessException;
use Hyperf\Database\Model\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class MySqlAccountRepositoryTest extends TestCase
{
    private MySqlAccountRepository $repository;
    private MockInterface|AccountModel $modelMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelMock = Mockery::mock(AccountModel::class);
        $this->repository = new MySqlAccountRepository($this->modelMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_byId_should_return_account_entity_when_found_without_lock(): void
    {
        $builder = $this->mockQueryBuilder();
        $modelData = (object) ['id' => 'acc-123', 'name' => 'Test User', 'balance_cents' => 50050];

        $builder->shouldReceive('where')->once()->with('id', 'acc-123')->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn($modelData);
        $builder->shouldNotReceive('lockForUpdate');

        $result = $this->repository->byId('acc-123', false);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertSame('acc-123', $result->id);
        $this->assertSame(50050, $result->balance->amountInCents);
    }

    public function test_byId_should_call_lockForUpdate_when_requested(): void
    {
        $builder = $this->mockQueryBuilder();
        $modelData = (object) ['id' => 'acc-123', 'name' => 'Test User', 'balance_cents' => 50050];

        $builder->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('id', 'acc-123')->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn($modelData);

        $result = $this->repository->byId('acc-123', true);

        $this->assertInstanceOf(Account::class, $result);
    }

    public function test_byId_should_return_null_when_account_not_found(): void
    {
        $builder = $this->mockQueryBuilder();
        $builder->shouldReceive('where')->once()->with('id', 'acc-not-found')->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn(null);

        $result = $this->repository->byId('acc-not-found');

        $this->assertNull($result);
    }

    public function test_save_should_call_updateOrCreate_with_correct_data(): void
    {
        $builder = $this->mockQueryBuilder();
        $accountEntity = new Account('acc-save', 'Saving User', Money::fromCentsForBalance(150000));

        $builder->shouldReceive('updateOrCreate')
            ->once()
            ->with(
                ['id' => 'acc-save'],
                Mockery::subset([
                    'name' => 'Saving User',
                    'balance_cents' => 150000
                ])
            );

        $this->repository->save($accountEntity);
        $this->assertTrue(true);
    }
//
//    public function test_lockById_should_return_account_when_found(): void
//    {
//        $builder = $this->mockQueryBuilder();
//        $modelData = (object) ['id' => 'acc-lock', 'name' => 'Locked User', 'balance_cents' => 200000];
//        $builder->shouldReceive('lockForUpdate')->once()->andReturnSelf();
//        $builder->shouldReceive('where')->once()->with('id', 'acc-lock')->andReturnSelf();
//        $builder->shouldReceive('first')->once()->andReturn($modelData);
//        $result = $this->repository->lockById('acc-lock');
//        $this->assertInstanceOf(Account::class, $result);
//        $this->assertSame('acc-lock', $result->id);
//    }
//
//    public function test_lockById_should_throw_exception_when_not_found(): void
//    {
//        $this->expectException(BusinessException::class);
//        $this->expectExceptionMessage('Account not found');
//        $builder = $this->mockQueryBuilder();
//        $builder->shouldReceive('lockForUpdate')->once()->andReturnSelf();
//        $builder->shouldReceive('where')->once()->with('id', 'acc-not-found')->andReturnSelf();
//        $builder->shouldReceive('first')->once()->andReturn(null);
//        $this->repository->lockById('acc-not-found');
//    }

    private function mockQueryBuilder(): MockInterface
    {
        $builder = Mockery::mock(Builder::class);
        $this->modelMock->shouldReceive('newQuery')->andReturn($builder);
        return $builder;
    }
}