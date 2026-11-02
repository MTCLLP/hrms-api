<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $activeYear = DB::table('calendar_years')->where('is_active', 1)->value('id');
        if ($activeYear) {
            DB::table('leave_balances')
                ->whereNull('calendar_year_id')
                ->update(['calendar_year_id' => $activeYear]);
        }

        $indexExists = collect(DB::select("SHOW INDEXES FROM leave_balances WHERE Key_name = 'unique_employee_leavetype_year'"))->isNotEmpty();
        if (!$indexExists) {
            Schema::table('leave_balances', function (Blueprint $table) {
                $table->unique(['employee_id', 'leavetype_id', 'calendar_year_id'], 'unique_employee_leavetype_year');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = collect(DB::select("SHOW INDEXES FROM leave_balances WHERE Key_name = 'unique_employee_leavetype_year'"))->isNotEmpty();
        if ($indexExists) {
            Schema::table('leave_balances', function (Blueprint $table) {
                $table->dropUnique('unique_employee_leavetype_year');
            });
        }
    }
};

