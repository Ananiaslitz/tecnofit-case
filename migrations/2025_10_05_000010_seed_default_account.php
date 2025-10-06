<?php
declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Db::table('account')->updateOrInsert(
            ['id' => '11111111-1111-1111-1111-111111111111'],
            [
                'name' => 'Tecnofit Demo',
                'balance' => 10_000_00
            ]
        );
    }

    public function down(): void
    {
        Db::table('account')->where('id', '11111111-1111-1111-1111-111111111111')->delete();
    }
};
