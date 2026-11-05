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
        Schema::create('ppdb_public_submissions', function (Blueprint $table) {
            $table->id();
            $table->char('idempotency_key_hash', 64);
            $table->char('success_reference_hash', 64);
            $table->foreignId('prospective_student_id')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_policy_version', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key_hash', 'ppdb_pub_idem_unique');
            $table->unique('success_reference_hash', 'ppdb_pub_ref_unique');
            $table->unique('prospective_student_id', 'ppdb_pub_ps_unique');
            $table->index('expires_at', 'ppdb_pub_expires_idx');
            $table->foreign('prospective_student_id', 'ppdb_pub_ps_fk')
                ->references('id')
                ->on('prospective_students')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_public_submissions');
    }
};
