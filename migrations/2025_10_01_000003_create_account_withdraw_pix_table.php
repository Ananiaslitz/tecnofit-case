<?php
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('account_withdraw_pix', function (Blueprint $t) {
            $t->uuid('account_withdraw_id')->primary();
            $t->string('type', 32);
            $t->string('key', 191);
            $t->string('key_hash', 64)->nullable();

            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $t->index(['key']);
            $t->index(['key_hash']);

            $t->foreign('account_withdraw_id')
                ->references('id')
                ->on('account_withdraw')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('account_withdraw_pix');
    }
};
