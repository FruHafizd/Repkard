<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedInteger('visits_total')->nullable()->after('visits_remaining');
            $table->unsignedBigInteger('credit_amount')->default(0)->after('visits_total');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
            $table->foreignId('replaced_by_id')->nullable()->after('cancel_reason')->constrained('memberships')->nullOnDelete();
        });

        // Baris lama: kuota awal = kuota kunjungan paketnya.
        DB::statement('UPDATE memberships SET visits_total = (SELECT visit_quota FROM membership_plans WHERE membership_plans.id = memberships.membership_plan_id)');

        Schema::table('payments', function (Blueprint $table) {
            $table->string('type')->default('purchase')->after('membership_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_id');
            $table->dropColumn(['visits_total', 'credit_amount', 'cancel_reason']);
        });
    }
};
