<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountWithdrawModel;
use Core\Adapter\Out\Persistence\MySqlWithdrawReadRepository;
use Core\Shared\Sanitization\PixMaskerInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Adapter\Out\Persistence\MySqlWithdrawReadRepository
 */
final class MySqlWithdrawReadRepositoryTest extends TestCase
{
    private MySqlWithdrawReadRepository $repository;
    private MockInterface|AccountWithdrawModel $withdrawModelMock;
    private MockInterface|PixMaskerInterface $pixMaskerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withdrawModelMock = Mockery::mock(AccountWithdrawModel::class);
        $this->pixMaskerMock = Mockery::mock(PixMaskerInterface::class);
        $this->repository = new MySqlWithdrawReadRepository(
            $this->withdrawModelMock,
            $this->pixMaskerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }



    public function test_listByAccount_happy_path(): void
    {
        $accountId = 'acc-123';
        $page = 2;
        $perPage = 5;

        $mockWithdrawRow = (object) [
            'id' => 'wid-1', 'account_id' => $accountId, 'method' => 'PIX', 'amount_cents' => 10050,
            'scheduled_for' => '2025-10-10 12:00:00', 'processed_at' => null, 'done' => false,
            'error' => false, 'error_reason' => null, 'created_at' => '2025-10-05 20:00:00',
            'updated_at' => '2025-10-05 20:00:00',
            'pix' => (object) ['type' => 'email', 'key' => 'test@example.com'],
        ];

        $builder = $this->mockQueryChain($accountId, ($page - 1) * $perPage, $perPage);
        $builder->shouldReceive('count')->once()->andReturn(15); // Total de 15 registros no DB
        $builder->shouldReceive('get')->once()->andReturn(new Collection([$mockWithdrawRow]));

        $this->pixMaskerMock->shouldReceive('mask')
            ->once()
            ->with('email', 'test@example.com')
            ->andReturn('te***@example.com');

        $result = $this->repository->listByAccount($accountId, $page, $perPage);

        $this->assertIsArray($result);
        $this->assertSame(15, $result['total']);
        $this->assertCount(1, $result['items']);

        $item = $result['items'][0];
        $this->assertSame('wid-1', $item['id']);
        $this->assertSame(100.50, $item['amount']);
        $this->assertSame(10050, $item['amount_cents']);
        $this->assertNotNull($item['pix']);
        $this->assertSame('te***@example.com', $item['pix']['key']);
    }

    public function test_listByAccount_sanitizes_pagination(): void
    {
        $accountId = 'acc-456';
        $page = 0;
        $perPage = 200;

        $builder = $this->mockQueryChain($accountId, 0, 100);
        $builder->shouldReceive('count')->once()->andReturn(0);
        $builder->shouldReceive('get')->once()->andReturn(new Collection());

        $result = $this->repository->listByAccount($accountId, $page, $perPage);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['items']);
    }

    /**
     * @dataProvider amountCalculationProvider
     */
    public function test_listByAccount_calculates_amounts_correctly(?float $inputAmount, ?int $inputCents, ?float $expectedAmount, ?int $expectedCents): void
    {
        $mockWithdrawRow = (object) [
            'id' => 'wid-amt', 'amount' => $inputAmount, 'amount_cents' => $inputCents,
            'pix' => null,
        ];

        $builder = $this->mockQueryChain('acc-any', 0, 1);
        $builder->shouldReceive('count')->andReturn(1);
        $builder->shouldReceive('get')->andReturn(new Collection([$mockWithdrawRow]));

        $result = $this->repository->listByAccount('acc-any', 1, 1);

        $item = $result['items'][0];
        $this->assertSame($expectedAmount, $item['amount']);
        $this->assertSame($expectedCents, $item['amount_cents']);
    }

    public static function amountCalculationProvider(): array
    {
        return [
            'amount_is_set'           => ['inputAmount' => 12.34, 'inputCents' => null, 'expectedAmount' => 12.34, 'expectedCents' => 1234],
            'amount_cents_is_set'     => ['inputAmount' => null, 'inputCents' => 5678, 'expectedAmount' => 56.78, 'expectedCents' => 5678],
            'both_are_set (amount_cents wins)' => ['inputAmount' => 10.00, 'inputCents' => 9999, 'expectedAmount' => 10.00, 'expectedCents' => 9999],
            'both_are_null'           => ['inputAmount' => null, 'inputCents' => null, 'expectedAmount' => null, 'expectedCents' => null],
        ];
    }

    private function mockQueryChain(string $accountId, int $expectedOffset, int $expectedLimit): MockInterface
    {
        $builder = Mockery::mock(Builder::class);
        $this->withdrawModelMock->shouldReceive('newQuery')->andReturn($builder);

        $builder->shouldReceive('where')->once()->with('account_id', $accountId)->andReturnSelf();
        $builder->shouldReceive('clone')->once()->andReturnSelf();

        $builder->shouldReceive('with')->once()->with('pix')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->once()->with('created_at')->andReturnSelf();
        $builder->shouldReceive('offset')->once()->with($expectedOffset)->andReturnSelf();
        $builder->shouldReceive('limit')->once()->with($expectedLimit)->andReturnSelf();

        return $builder;
    }
}