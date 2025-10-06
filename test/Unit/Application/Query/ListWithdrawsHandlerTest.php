<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\Query;

use Core\Application\Query\ListWithdrawsHandler;
use Core\Application\Query\ListWithdrawsQuery;
use Core\Domain\Port\WithdrawReadRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Application\Query\ListWithdrawsHandler
 */
final class ListWithdrawsHandlerTest extends TestCase
{
    private MockInterface|WithdrawReadRepository $repoMock;
    private ListWithdrawsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoMock = Mockery::mock(WithdrawReadRepository::class);
        $this->handler = new ListWithdrawsHandler($this->repoMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_invoke_returns_formatted_data_on_happy_path(): void
    {
        $query = new ListWithdrawsQuery(accountId: 'acc-123', page: 2, perPage: 15);

        $repoResult = [
            'total' => 45,
            'items' => [['id' => 'wid-1'], ['id' => 'wid-2']],
        ];

        $this->repoMock->shouldReceive('listByAccount')
            ->once()
            ->with('acc-123', 2, 15)
            ->andReturn($repoResult);

        $result = ($this->handler)($query);

        $expected = [
            'ok'       => true,
            'page'     => 2,
            'per_page' => 15,
            'total'    => 45,
            'items'    => [['id' => 'wid-1'], ['id' => 'wid-2']],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider paginationSanitizationProvider
     */
    public function test_invoke_sanitizes_pagination_parameters(int $inputPage, int $inputPerPage, int $expectedPage, int $expectedPerPage): void
    {
        $query = new ListWithdrawsQuery(accountId: 'acc-456', page: $inputPage, perPage: $inputPerPage);

        $this->repoMock->shouldReceive('listByAccount')
            ->once()
            ->with('acc-456', $expectedPage, $expectedPerPage)
            ->andReturn(['total' => 0, 'items' => []]);

        $result = ($this->handler)($query);

        $this->assertSame($expectedPage, $result['page']);
        $this->assertSame($expectedPerPage, $result['per_page']);
    }

    public static function paginationSanitizationProvider(): array
    {
        return [
            'page less than 1'    => ['inputPage' => 0, 'inputPerPage' => 20, 'expectedPage' => 1, 'expectedPerPage' => 20],
            'perPage less than 1' => ['inputPage' => 1, 'inputPerPage' => -5, 'expectedPage' => 1, 'expectedPerPage' => 1],
            'perPage more than 100' => ['inputPage' => 2, 'inputPerPage' => 200, 'expectedPage' => 2, 'expectedPerPage' => 100],
            'both invalid'        => ['inputPage' => -1, 'inputPerPage' => 0, 'expectedPage' => 1, 'expectedPerPage' => 1],
            'valid values'        => ['inputPage' => 5, 'inputPerPage' => 50, 'expectedPage' => 5, 'expectedPerPage' => 50],
        ];
    }
}