<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pt_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained('users');
            $table->unsignedInteger('sessions_total');
            $table->unsignedInteger('sessions_remaining');
            $table->date('starts_on');
            $table->date('expires_on');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_subscriptions');
    }
};
