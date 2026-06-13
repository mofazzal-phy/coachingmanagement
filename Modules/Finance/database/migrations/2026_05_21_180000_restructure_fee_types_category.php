<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add category column (nullable initially to migrate data)
        Schema::table('fee_types', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('description');
        });

        // Step 2: Migrate existing frequency data to category
        DB::statement("UPDATE fee_types SET category = 'one_time' WHERE frequency = 'one_time'");
        DB::statement("UPDATE fee_types SET category = 'monthly' WHERE frequency = 'monthly'");
        DB::statement("UPDATE fee_types SET category = 'event_based' WHERE frequency IN ('yearly', 'term')");

        // Step 3: Make category NOT NULL after data migration
        Schema::table('fee_types', function (Blueprint $table) {
            $table->string('category', 20)->nullable(false)->change();
        });

        // Step 4: Drop the old frequency column
        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->enum('frequency', ['one_time', 'monthly', 'yearly', 'term'])->default('monthly')->after('description');
        });

        // Restore frequency from category
        DB::statement("UPDATE fee_types SET frequency = 'one_time' WHERE category = 'one_time'");
        DB::statement("UPDATE fee_types SET frequency = 'monthly' WHERE category = 'monthly'");
        DB::statement("UPDATE fee_types SET frequency = 'yearly' WHERE category = 'event_based'");

        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
