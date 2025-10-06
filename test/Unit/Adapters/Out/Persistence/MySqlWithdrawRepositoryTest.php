<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountWithdrawModel;
use Core\Adapter\Out\Persistence\Model\AccountWithdrawPixModel;
use Core\Adapter\Out\Persistence\MySqlWithdrawRepository;
use Core\Domain\Entity\Withdraw;
use Core\Domain\Port\Clock;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use DateTimeImmutable;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Adapter\Out\Persistence\MySqlWithdrawRepository
 */
final class MySqlWithdrawRepositoryTest extends TestCase
{
    private MySqlWithdrawRepository $repository;
    private MockInterface|AccountWithdrawModel $withdrawModelMock;
    private MockInterface|AccountWithdrawPixModel $pixModelMock;
    private MockInterface|Clock $clockMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withdrawModelMock = Mockery::mock(AccountWithdrawModel::class);
        $this->pixModelMock = Mockery::mock(AccountWithdrawPixModel::class);
        $this->clockMock = Mockery::mock(Clock::class);

        $this->repository = new MySqlWithdrawRepository(
            $this->withdrawModelMock,
            $this->pixModelMock,
            $this->clockMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_save_creates_withdraw_and_pix_data(): void
    {
        $withdrawBuilder = $this->mockBuilder($this->withdrawModelMock);
        $pixBuilder = $this->mockBuilder($this->pixModelMock);
        $id = 'wid-1';
        $accountId = 'acc-1';

        $amount = Money::fromDecimal(50.00);

        $pix = new PixKey('email', 'test@test.com');
        $scheduledFor = new DateTimeImmutable('2025-10-10 12:00:00');

        $withdrawBuilder->shouldReceive('updateOrCreate')
            ->once()
            ->with(['id' => $id], [
                'account_id'    => $accountId,
                'amount'        => 50.00,
                'amount_cents'  => 5000,
                'method'        => 'PIX',
                'scheduled'     => true,
                'scheduled_for' => '2025-10-10 12:00:00',
            ]);

        $pixBuilder->shouldReceive('updateOrCreate')
            ->once()
            ->with(['account_withdraw_id' => $id], ['type' => 'email', 'key' => 'test@test.com']);

        $this->repository->save($id, $accountId, $amount, 'PIX', true, $scheduledFor, $pix);

        $this->assertTrue(true);
    }

    public function test_save_does_not_save_pix_when_it_is_null(): void
    {
        $withdrawBuilder = $this->mockBuilder($this->withdrawModelMock);
        $withdrawBuilder->shouldReceive('updateOrCreate')->once();
        $this->pixModelMock->shouldNotReceive('newQuery');
        $this->repository->save('wid-1', 'acc-1', Money::fromDecimal(100), 'PIX', false, null, null);
        $this->assertTrue(true);
    }

    public function test_markDone_updates_record_correctly(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $now = new DateTimeImmutable('2025-10-05 22:00:00');
        $this->clockMock->shouldReceive('now')->once()->andReturn($now);
        $builder->shouldReceive('where')->once()->with('id', 'wid-done')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['done' => true, 'error' => false, 'error_reason' => null, 'updated_at' => '2025-10-05 22:00:00']);
        $this->repository->markDone('wid-done');
        $this->assertTrue(true);
    }

    public function test_markFailed_updates_record_correctly(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $now = new DateTimeImmutable('2025-10-05 22:10:00');
        $this->clockMock->shouldReceive('now')->once()->andReturn($now);
        $builder->shouldReceive('where')->once()->with('id', 'wid-fail')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['done' => true, 'error' => true, 'error_reason' => 'INSUFFICIENT_FUNDS', 'updated_at' => '2025-10-05 22:10:00']);
        $this->repository->markFailed('wid-fail', 'INSUFFICIENT_FUNDS');
        $this->assertTrue(true);
    }


    public function test_byId_finds_and_reconstructs_withdraw_with_pix(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $modelData = (object)['id' => 'wid-123', 'account_id' => 'acc-456', 'amount_cents' => 7550, 'method' => 'PIX', 'scheduled' => true, 'scheduled_for' => '2025-10-11 10:00:00', 'done' => false, 'error' => false, 'error_reason' => null, 'pix' => (object)['type' => 'phone', 'key' => '+5511987654321'],];
        $builder->shouldReceive('with')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('find')->once()->with('wid-123')->andReturn($modelData);
        $result = $this->repository->byId('wid-123');
        $this->assertInstanceOf(Withdraw::class, $result);
        $this->assertEquals(7550, $result->amount->amountInCents);
        $this->assertInstanceOf(PixKey::class, $result->pix);
    }

    public function test_byId_returns_null_when_not_found(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $builder->shouldReceive('with')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('find')->once()->with('wid-not-found')->andReturn(null);
        $result = $this->repository->byId('wid-not-found');
        $this->assertNull($result);
    }

    public function test_byId_returns_null_if_pix_is_missing(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $modelDataWithoutPix = (object)['id' => 'wid-123', 'pix' => null];
        $builder->shouldReceive('with')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('find')->once()->with('wid-123')->andReturn($modelDataWithoutPix);

        $result = $this->repository->byId('wid-123');

        $this->assertNull($result);
    }

    public function test_findDueScheduled_returns_correctly_hydrated_entities(): void
    {
        $builder = $this->mockBuilder($this->withdrawModelMock);
        $now = new DateTimeImmutable();

        $modelData2 = (object)[
            'id' => 'wid-due-2',
            'account_id' => 'acc-b',
            'amount_cents' => 200,
            'pix' => (object)[
                'type' => 'email',
                'key' => 'b@b.com'
            ],
            'cheduled' => true,
            'scheduled_for' => $now->format('Y-m-d H:i:s'),
            'method' => 'PIX',
            'done' => false,
            'error' => false,
            'error_reason' => null
        ];

        $builder->shouldReceive('has')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('with')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('scheduled', 1)->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('done', 0)->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('scheduled_for', '<=', $now->format('Y-m-d H:i:s'))->andReturnSelf();
        $builder->shouldReceive('orderBy')->once()->with('scheduled_for', 'asc')->andReturnSelf();
        $builder->shouldReceive('limit')->once()->with(50)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(new Collection([$modelData2]));

        $results = $this->repository->findDueScheduled($now, 50);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(Withdraw::class, $results[0]);
        $this->assertSame('wid-due-2', $results[0]->id);
        $this->assertInstanceOf(PixKey::class, $results[0]->pix);
    }

    private function mockBuilder(MockInterface $modelMock): MockInterface
    {
        $builder = Mockery::mock(Builder::class);
        $modelMock->shouldReceive('newQuery')->andReturn($builder);
        return $builder;
    }
}