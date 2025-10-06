<?php
declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('account_withdraw')) {
            return;
        }

        if (! $this->indexExists('account_withdraw', 'idx_withdraw_account_created')) {
            Schema::table('account_withdraw', function (Blueprint $table) {
                $table->index(['account_id', 'created_at'], 'idx_withdraw_account_created');
            });
        }

        if (! $this->indexExists('account_withdraw', 'idx_withdraw_scheduled_done')) {
            Schema::table('account_withdraw', function (Blueprint $table) {
                $table->index(['scheduled_for', 'done'], 'idx_withdraw_scheduled_done');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_withdraw')) {
            return;
        }

        if ($this->indexExists('account_withdraw', 'idx_withdraw_account_created')) {
            Schema::table('account_withdraw', function (Blueprint $table) {
                $table->dropIndex('idx_withdraw_account_created');
            });
        }

        if ($this->indexExists('account_withdraw', 'idx_withdraw_scheduled_done')) {
            Schema::table('account_withdraw', function (Blueprint $table) {
                $table->dropIndex('idx_withdraw_scheduled_done');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = Db::selectOne('select database() as db');
        $schema = is_array($db) ? $db['db'] ?? null : ($db->db ?? null);
        if (! $schema) return false;

        $row = Db::selectOne(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$schema, $table, $index]
        );
        return (bool) $row;
    }
};
