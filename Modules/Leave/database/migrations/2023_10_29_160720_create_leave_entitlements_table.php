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
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('leavetype_id')->unsigned()->index()->nullable();
            $table->foreign('leavetype_id')->references('id')->on('leave_types')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('employee_id')->unsigned()->index()->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->string('ent_amount'); //total days entitled
            $table->date('ent_start_date');
            $table->date('ent_end_date');
            $table->bigInteger('created_by')->unsigned()->index()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trashed')->default(false);
            $table->timestamp('deleted_at')->nullable();
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
        Schema::dropIfExists('leave_entitlements');
    }
};
