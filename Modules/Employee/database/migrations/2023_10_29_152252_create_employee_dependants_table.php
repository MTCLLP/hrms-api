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
        Schema::create('employee_dependants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('employee_id')->unsigned()->index()->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null')->onUpdate('cascade');
            $table->string('name');
            $table->string('number')->nullable();
            $table->string('email')->nullable();
            $table->string('relationship');//Spouse, Parent, Child etc.
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
        Schema::dropIfExists('employee_dependants');
    }
};
