<?php

namespace Modules\Exam\app\Support;

use Modules\Student\app\Models\Student;

trait ResolvesStudentFromAuth
{
    protected function resolveStudentId(): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $student = Student::where('user_id', $user->id)->where('status', 'active')->first();
        if ($student) {
            return $student->id;
        }

        if (!empty($user->email)) {
            $student = Student::where('email', $user->email)->where('status', 'active')->first();
            if ($student) {
                if (!$student->user_id) {
                    $student->update(['user_id' => $user->id]);
                }
                return $student->id;
            }
        }

        if (!empty($user->phone)) {
            $student = Student::where('phone', $user->phone)->where('status', 'active')->first();
            if ($student) {
                if (!$student->user_id) {
                    $student->update(['user_id' => $user->id]);
                }
                return $student->id;
            }
        }

        return '';
    }
}
