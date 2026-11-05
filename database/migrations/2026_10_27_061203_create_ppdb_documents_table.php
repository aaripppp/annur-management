<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('disk', 40);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['prospective_student_id', 'document_type'], 'ppdb_docs_ps_dt_unique');
            $table->index('document_type', 'ppdb_docs_dt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_documents');
    }
};
