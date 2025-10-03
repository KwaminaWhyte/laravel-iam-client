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
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('department_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('level', ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])->default('entry');
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->uuid('reports_to_position_id')->nullable();
            $table->timestamps();

            $table->index(['department_id']);
            $table->index(['reports_to_position_id']);
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
        });

        // Add self-referencing foreign key after table creation
        Schema::table('positions', function (Blueprint $table) {
            $table->foreign('reports_to_position_id')->references('id')->on('positions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['reports_to_position_id']);
            $table->dropForeign(['department_id']);
        });
        Schema::dropIfExists('positions');
    }
};
