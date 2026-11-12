<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('discount_type');
            $table->unsignedInteger('discount_value');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_member')->default(1);
            $table->boolean('new_members_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('membership_plan_promo_code', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->primary(['membership_plan_id', 'promo_code_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('original_amount')->nullable()->after('amount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('original_amount');
            $table->foreignId('promo_code_id')->nullable()->after('discount_amount')->constrained()->nullOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('requested_promo_code', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('requested_promo_code'));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['original_amount', 'discount_amount']);
        });
        Schema::dropIfExists('membership_plan_promo_code');
        Schema::dropIfExists('promo_codes');
    }
};
