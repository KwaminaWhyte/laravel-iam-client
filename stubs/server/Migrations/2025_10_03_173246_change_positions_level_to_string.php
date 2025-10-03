<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change level column from enum to string
        DB::statement("ALTER TABLE positions ALTER COLUMN level DROP DEFAULT");
        DB::statement("ALTER TABLE positions ALTER COLUMN level TYPE VARCHAR(50) USING level::text");
        DB::statement("ALTER TABLE positions ALTER COLUMN level SET DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to enum
        DB::statement("ALTER TABLE positions ALTER COLUMN level DROP DEFAULT");
        DB::statement("ALTER TABLE positions ALTER COLUMN level TYPE VARCHAR(50)");

        // Create enum type if it doesn't exist
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'positions_level_enum') THEN
                    CREATE TYPE positions_level_enum AS ENUM ('entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive');
                END IF;
            END$$;
        ");

        DB::statement("ALTER TABLE positions ALTER COLUMN level TYPE positions_level_enum USING level::positions_level_enum");
        DB::statement("ALTER TABLE positions ALTER COLUMN level SET DEFAULT 'entry'");
    }
};
