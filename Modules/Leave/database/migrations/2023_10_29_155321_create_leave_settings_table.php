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
        Schema::create('leave_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('leavetype_id')->unsigned()->index()->nullable();
            $table->foreign('leavetype_id')->references('id')->on('leave_types')->onDelete('set null')->onUpdate('cascade');
            $table->string('accrual_method'); //Annual, Monthly, etc.
            $table->string('accrual_rate'); //days per year, days per month etc.
            $table->string('maximum_accrual');//Maximum days that can be accrued
            $table->boolean('allow_negative_bal')->default(1);
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
        Schema::dropIfExists('leave_settings');
    }
};
