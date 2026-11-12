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
        Schema::create('member_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('contacted_at');
            $table->string('channel');
            $table->string('outcome');
            $table->text('note')->nullable();
            $table->date('next_contact_on')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'contacted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_follow_ups');
    }
};
