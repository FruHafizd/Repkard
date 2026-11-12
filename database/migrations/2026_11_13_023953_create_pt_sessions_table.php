<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('status')->default('scheduled');
            $table->unsignedBigInteger('commission_amount')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->foreignId('finished_by')->nullable()->constrained('users');
            $table->string('cancel_reason')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['trainer_id', 'scheduled_at']);
            $table->index(['status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_sessions');
    }
};
