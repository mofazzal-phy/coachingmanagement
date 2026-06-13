<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if routine_type column already exists (from partial migration)
        $columns = DB::select('SHOW COLUMNS FROM class_routines WHERE Field = ?', ['routine_type']);
        if (empty($columns)) {
            // Drop foreign keys that reference columns in the unique index
            DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_academic_session_id_foreign`');
            DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_class_id_foreign`');
            DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_section_id_foreign`');
            DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_period_id_foreign`');
            
            // Now drop the unique index
            DB::statement('ALTER TABLE `class_routines` DROP INDEX IF EXISTS `routine_unique_class_period`');
            
            // Add the routine_type column
            DB::statement("ALTER TABLE `class_routines` ADD `routine_type` ENUM('weekly', 'daily') NOT NULL DEFAULT 'weekly' AFTER `status`");
        }
        
        // Re-create unique constraint with routine_type (if not exists)
        $indexes = DB::select('SHOW INDEX FROM class_routines WHERE Key_name = ?', ['routine_unique_class_period']);
        if (empty($indexes)) {
            DB::statement('ALTER TABLE `class_routines` ADD UNIQUE KEY `routine_unique_class_period` (`academic_session_id`, `class_id`, `section_id`, `day_of_week`, `period_id`, `routine_type`)');
        }
        
        // Re-add foreign keys (if they were dropped)
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'class_routines_academic_session_id_foreign'");
        if (empty($fks)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE');
        }
        
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'class_routines_class_id_foreign'");
        if (empty($fks)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE');
        }
        
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'class_routines_section_id_foreign'");
        if (empty($fks)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL');
        }
        
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'class_routines_period_id_foreign'");
        if (empty($fks)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `routine_periods` (`id`) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_academic_session_id_foreign`');
        DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_class_id_foreign`');
        DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_section_id_foreign`');
        DB::statement('ALTER TABLE `class_routines` DROP FOREIGN KEY IF EXISTS `class_routines_period_id_foreign`');
        DB::statement('ALTER TABLE `class_routines` DROP INDEX IF EXISTS `routine_unique_class_period`');
        DB::statement('ALTER TABLE `class_routines` DROP COLUMN IF EXISTS `routine_type`');
        DB::statement('ALTER TABLE `class_routines` ADD UNIQUE KEY `routine_unique_class_period` (`academic_session_id`, `class_id`, `section_id`, `day_of_week`, `period_id`)');
        DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE');
        DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE');
        DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL');
        DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `routine_periods` (`id`) ON DELETE CASCADE');
    }
};
