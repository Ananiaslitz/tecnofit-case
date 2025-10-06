<?php
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('account_withdraw', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id');
            $t->string('method', 16);
            $t->decimal('amount', 12, 2);
            $t->unsignedBigInteger('amount_cents');

            $t->boolean('scheduled')->default(false);
            $t->dateTime('scheduled_for')->nullable();
            $t->dateTime('processed_at')->nullable();

            $t->boolean('done')->default(false);
            $t->boolean('error')->default(false);
            $t->string('error_reason', 255)->nullable();

            $t->string('idempotency_key', 64)->nullable();

            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $t->index(['account_id']);
            $t->index(['scheduled_for', 'done'], 'idx_withdraw_scheduled_done');
            $t->index(['account_id', 'created_at'], 'idx_withdraw_account_created');

            $t->unique(['account_id', 'idempotency_key'], 'uq_withdraw_idem_per_account');
        });
    }

    public function down(): void {
        Schema::dropIfExists('account_withdraw');
    }
};
