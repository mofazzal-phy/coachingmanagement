<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Exam\app\Models\ExamResult;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_results', 'marks_breakdown')) {
                $table->json('marks_breakdown')->nullable()->after('marks_obtained');
            }
            if (!Schema::hasColumn('exam_results', 'evaluation_status')) {
                $table->enum('evaluation_status', ['pending', 'partial', 'complete'])
                    ->default('pending')
                    ->after('status');
            }
        });

        Schema::table('exam_routines', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_routines', 'mark_config')) {
                $table->json('mark_config')->nullable()->after('pass_marks');
            }
            if (!Schema::hasColumn('exam_routines', 'instructions')) {
                $table->text('instructions')->nullable()->after('mark_config');
            }
        });

        ExamResult::query()
            ->whereNotNull('marks_obtained')
            ->where('status', '!=', 'absent')
            ->chunkById(200, function ($results) {
                foreach ($results as $result) {
                    $result->update([
                        'evaluation_status' => 'complete',
                        'marks_breakdown' => ['total' => (float) $result->marks_obtained],
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            if (Schema::hasColumn('exam_results', 'marks_breakdown')) {
                $table->dropColumn('marks_breakdown');
            }
            if (Schema::hasColumn('exam_results', 'evaluation_status')) {
                $table->dropColumn('evaluation_status');
            }
        });

        Schema::table('exam_routines', function (Blueprint $table) {
            if (Schema::hasColumn('exam_routines', 'mark_config')) {
                $table->dropColumn('mark_config');
            }
            if (Schema::hasColumn('exam_routines', 'instructions')) {
                $table->dropColumn('instructions');
            }
        });
    }
};
