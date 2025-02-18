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
        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('leaverequest_id')->unsigned()->index()->nullable();
            $table->foreign('leaverequest_id')->references('id')->on('leave_requests')->onDelete('cascade')->onUpdate('cascade');
            $table->bigInteger('approver_id')->unsigned()->index()->nullable();
            $table->foreign('approver_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->float('total_days');
            $table->boolean('isPaidLeave');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'ApprovedWithoutPay'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trashed')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
    }
};
