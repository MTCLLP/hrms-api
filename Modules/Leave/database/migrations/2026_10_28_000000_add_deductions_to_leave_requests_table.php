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
        if (Schema::hasTable('leave_requests') && !Schema::hasColumn('leave_requests', 'deductions')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->json('deductions')->nullable()->after('is_deducted');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'deductions')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropColumn('deductions');
            });
        }
    }
};
