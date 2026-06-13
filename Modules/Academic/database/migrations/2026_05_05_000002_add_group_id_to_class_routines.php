<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if group_id column already exists
        $columns = DB::select('SHOW COLUMNS FROM class_routines WHERE Field = ?', ['group_id']);
        if (empty($columns)) {
            // Drop foreign keys that reference columns in the unique index
            // Use information_schema to check if FK exists before dropping
            $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
            $fkNames = array_column($fks, 'CONSTRAINT_NAME');
            
            $targetFks = ['class_routines_academic_session_id_foreign', 'class_routines_class_id_foreign', 'class_routines_section_id_foreign', 'class_routines_period_id_foreign'];
            foreach ($targetFks as $fkName) {
                if (in_array($fkName, $fkNames)) {
                    DB::statement("ALTER TABLE `class_routines` DROP FOREIGN KEY `{$fkName}`");
                }
            }
            
            // Drop the unique index if it exists
            $indexes = DB::select("SHOW INDEX FROM class_routines WHERE Key_name = 'routine_unique_class_period'");
            if (!empty($indexes)) {
                DB::statement('ALTER TABLE `class_routines` DROP INDEX `routine_unique_class_period`');
            }
            
            // Add group_id column (BIGINT UNSIGNED to match academic_groups.id)
            DB::statement('ALTER TABLE `class_routines` ADD `group_id` BIGINT UNSIGNED NULL AFTER `section_id`');
            
            // Make day_of_week nullable for daily routines
            DB::statement('ALTER TABLE `class_routines` MODIFY `day_of_week` ENUM("sat","sun","mon","tue","wed","thu","fri") NULL');
        }
        
        // Re-create unique constraint with group_id
        $indexes = DB::select("SHOW INDEX FROM class_routines WHERE Key_name = 'routine_unique_class_period'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE `class_routines` ADD UNIQUE KEY `routine_unique_class_period` (`academic_session_id`, `class_id`, `section_id`, `group_id`, `day_of_week`, `period_id`, `routine_type`)');
        }
        
        // Re-add foreign keys (if they were dropped)
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
        $fkNames = array_column($fks, 'CONSTRAINT_NAME');
        
        if (!in_array('class_routines_academic_session_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE');
        }
        
        if (!in_array('class_routines_class_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE');
        }
        
        if (!in_array('class_routines_section_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL');
        }
        
        if (!in_array('class_routines_period_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `routine_periods` (`id`) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        // Check existing foreign keys
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
        $fkNames = array_column($fks, 'CONSTRAINT_NAME');
        
        $targetFks = ['class_routines_academic_session_id_foreign', 'class_routines_class_id_foreign', 'class_routines_section_id_foreign', 'class_routines_period_id_foreign'];
        foreach ($targetFks as $fkName) {
            if (in_array($fkName, $fkNames)) {
                DB::statement("ALTER TABLE `class_routines` DROP FOREIGN KEY `{$fkName}`");
            }
        }
        
        // Drop the unique index if it exists
        $indexes = DB::select("SHOW INDEX FROM class_routines WHERE Key_name = 'routine_unique_class_period'");
        if (!empty($indexes)) {
            DB::statement('ALTER TABLE `class_routines` DROP INDEX `routine_unique_class_period`');
        }
        
        // Drop group_id column if it exists
        $columns = DB::select('SHOW COLUMNS FROM class_routines WHERE Field = ?', ['group_id']);
        if (!empty($columns)) {
            DB::statement('ALTER TABLE `class_routines` DROP COLUMN `group_id`');
        }
        
        // Restore day_of_week to NOT NULL
        DB::statement('ALTER TABLE `class_routines` MODIFY `day_of_week` ENUM("sat","sun","mon","tue","wed","thu","fri") NOT NULL');
        
        // Re-create original unique index
        $indexes = DB::select("SHOW INDEX FROM class_routines WHERE Key_name = 'routine_unique_class_period'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE `class_routines` ADD UNIQUE KEY `routine_unique_class_period` (`academic_session_id`, `class_id`, `section_id`, `day_of_week`, `period_id`, `routine_type`)');
        }
        
        // Re-add foreign keys
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_routines' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
        $fkNames = array_column($fks, 'CONSTRAINT_NAME');
        
        if (!in_array('class_routines_academic_session_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE');
        }
        if (!in_array('class_routines_class_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE');
        }
        if (!in_array('class_routines_section_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL');
        }
        if (!in_array('class_routines_period_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE `class_routines` ADD CONSTRAINT `class_routines_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `routine_periods` (`id`) ON DELETE CASCADE');
        }
    }
};
