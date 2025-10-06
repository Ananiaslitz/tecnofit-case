<?php
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('account', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->decimal('balance', 12, 2)->default(0);
        });
    }
    public function down(): void { Schema::dropIfExists('account'); }
};
