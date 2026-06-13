<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exam_routines')) {
            return;
        }

        Schema::table('exam_routines', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_routines', 'delivery_mode')) {
                $table->string('delivery_mode', 20)->default('offline')->after('status');
            }
        });

        if (Schema::hasColumn('exam_routines', 'delivery_mode') && Schema::hasTable('exams')) {
            DB::statement("
                UPDATE exam_routines er
                INNER JOIN exams e ON er.exam_id = e.id
                SET er.delivery_mode = COALESCE(NULLIF(e.delivery_mode, ''), 'offline')
                WHERE er.delivery_mode IS NULL OR er.delivery_mode = ''
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('exam_routines')) {
            return;
        }

        Schema::table('exam_routines', function (Blueprint $table) {
            if (Schema::hasColumn('exam_routines', 'delivery_mode')) {
                $table->dropColumn('delivery_mode');
            }
        });
    }
};
