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
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('parent_department_id')->nullable();
            $table->uuid('manager_id')->nullable();
            $table->timestamps();

            $table->unique(['name']);
            $table->index(['parent_department_id']);
        });

        // Add foreign keys after table creation
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_department_id']);
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('departments');
    }
};
