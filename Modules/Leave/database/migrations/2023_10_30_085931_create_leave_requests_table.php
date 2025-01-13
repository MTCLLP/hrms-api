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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('employee_id')->unsigned()->index()->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->bigInteger('leavetype_id')->unsigned()->index()->nullable();
            $table->foreign('leavetype_id')->references('id')->on('leave_types')->onDelete('set null')->onUpdate('cascade');
            $table->string('status')->default('Pending');//Approved, Pending
            $table->string('comments');
            $table->bigInteger('created_by')->unsigned()->index()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('supervised_by')->unsigned()->index()->nullable();
            $table->foreign('supervised_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_requests');
    }
};
