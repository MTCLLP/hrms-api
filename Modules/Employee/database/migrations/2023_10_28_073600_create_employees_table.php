<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->date('dob');
            $table->string('gender');
            $table->bigInteger('user_id')->unsigned()->index()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('jobtTitle_id')->unsigned()->index()->nullable();
            $table->foreign('jobtTitle_id')->references('id')->on('job_titles')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('department_id')->unsigned()->index()->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null')->onUpdate('cascade');
            $table->string('profile_image')->nullable();
            $table->bigInteger('created_by')->unsigned()->index()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trashed')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        /*
        Employee
        Department
        Job Title
        EmpReporting (see)
        EmpReportingMethod (see)
        Attendance
        Job Openings
        Applicants
        Interviews
        Leave Requests
        LeaveTypes
        LeaveSettings
        LeaveEntitlements
        LeaveBalance
        */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
