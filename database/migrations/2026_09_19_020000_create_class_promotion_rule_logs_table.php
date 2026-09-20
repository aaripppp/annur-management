<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_promotion_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_class_id')->nullable();
            $table->index('source_class_id');
            $table->unsignedBigInteger('class_promotion_rule_id')->nullable();
            $table->index('class_promotion_rule_id');
            $table->string('action_type', 40);
            $table->string('old_action', 20)->nullable();
            $table->unsignedBigInteger('old_target_class_id')->nullable();
            $table->string('old_source_type', 20)->nullable();
            $table->string('new_action', 20)->nullable();
            $table->unsignedBigInteger('new_target_class_id')->nullable();
            $table->string('new_source_type', 20)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_promotion_rule_logs');
    }
};
