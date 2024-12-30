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
        Schema::create('job_reportings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('superior_id')->unsigned()->index()->nullable();
            $table->foreign('superior_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('subordinate_id')->unsigned()->index()->nullable();
            $table->foreign('subordinate_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->bigInteger('reporting_method')->unsigned()->index()->nullable();
            $table->foreign('reporting_method')->references('id')->on('reporting_methods')->onDelete('set null')->onUpdate('cascade');
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
        Schema::dropIfExists('job_reportings');
    }
};
