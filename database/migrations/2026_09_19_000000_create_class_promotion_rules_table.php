<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('target_class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('source_class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_promotion_rules');
    }
};
