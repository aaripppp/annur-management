<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ppdb_document_cleanup_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ppdb_document_id')->nullable();
            $table->unsignedBigInteger('prospective_student_id')->nullable();
            $table->string('disk', 40);
            $table->string('path', 1024);
            $table->char('path_hash', 64)->unique();
            $table->string('reason_code', 60);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'last_attempted_at'], 'ppdb_cleanup_pending_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_document_cleanup_failures');
    }
};
