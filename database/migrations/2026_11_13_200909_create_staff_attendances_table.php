<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->string('clock_in_photo_path');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_out_photo_path')->nullable();
            $table->boolean('is_late')->default(false);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
