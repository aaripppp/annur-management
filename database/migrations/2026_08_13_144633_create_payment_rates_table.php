<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_type_id')
                ->constrained('payment_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('class_level');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_monthly')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['payment_type_id', 'class_level']);
        });

        $sppTypeId = DB::table('payment_types')
            ->where('name', 'like', '%spp%')
            ->value('id');

        if ($sppTypeId) {
            DB::table('payment_rates')->insert(
                DB::table('spp_rates')
                    ->get(['class_level', 'amount', 'effective_from', 'effective_until'])
                    ->map(fn ($row) => [
                        'payment_type_id' => $sppTypeId,
                        'class_level' => $row->class_level,
                        'amount' => $row->amount,
                        'is_monthly' => true,
                        'effective_from' => $row->effective_from,
                        'effective_until' => $row->effective_until,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        }

        Schema::dropIfExists('spp_rates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('spp_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('class_level');
            $table->decimal('amount', 15, 2);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });

        $sppTypeId = DB::table('payment_types')
            ->where('name', 'like', '%spp%')
            ->value('id');

        if ($sppTypeId) {
            DB::table('spp_rates')->insert(
                DB::table('payment_rates')
                    ->where('payment_type_id', $sppTypeId)
                    ->get(['class_level', 'amount', 'effective_from', 'effective_until'])
                    ->map(fn ($row) => [
                        'class_level' => $row->class_level,
                        'amount' => $row->amount,
                        'effective_from' => $row->effective_from,
                        'effective_until' => $row->effective_until,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        }

        Schema::dropIfExists('payment_rates');
    }
};
