<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Marksheet - {{ $exam['name'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 18px; color: #4f46e5; }
        .header p { margin: 3px 0; color: #6b7280; font-size: 9px; }
        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 10px 0 14px; text-transform: uppercase; }
        .info { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .info td { padding: 3px 6px; font-size: 9px; }
        .info .lbl { font-weight: bold; color: #4b5563; width: 90px; }
        table.marks { width: 100%; border-collapse: collapse; }
        table.marks th { background: #4f46e5; color: #fff; padding: 5px 6px; font-size: 8px; text-align: left; }
        table.marks td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; font-size: 9px; }
        .summary { margin-top: 14px; border: 1px solid #c7d2fe; background: #f8fafc; padding: 10px; }
        .summary table { width: 100%; }
        .summary td { text-align: center; padding: 4px; }
        .summary .val { font-size: 14px; font-weight: bold; color: #4f46e5; }
        .summary .lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .merit-note { margin-top: 8px; font-size: 9px; color: #374151; text-align: center; }
        .footer { margin-top: 16px; text-align: center; font-size: 8px; color: #9ca3af; }
        .topper { color: #059669; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Academic Marksheet</h1>
        <p>{{ $exam['session'] ?? '' }} &bull; Published {{ $exam['published_at'] ?? 'N/A' }}</p>
    </div>

    <div class="title">{{ $exam['name'] }}</div>

    <table class="info">
        <tr>
            <td class="lbl">Student</td><td>{{ $student['name'] }}</td>
            <td class="lbl">ID / Roll</td><td>{{ $student['student_id'] }} / {{ $student['roll_no'] ?? '—' }}</td>
        </tr>
        @if(!empty($exam['delivery_channel']))
        <tr>
            <td class="lbl">Results</td><td colspan="3">{{ ucfirst($exam['delivery_channel']) }} exam</td>
        </tr>
        @endif
        @if(!empty($merit_scope))
        <tr>
            <td class="lbl">Merit scope</td><td colspan="3">{{ $merit_scope['label'] ?? '' }}</td>
        </tr>
        @endif
        @if(!empty($standing))
        <tr>
            <td class="lbl">Position</td><td>{{ $standing['position'] }} of {{ $standing['total_students'] }}</td>
            <td class="lbl">Overall GPA</td><td>{{ $standing['gpa'] ?? '—' }} ({{ $standing['overall_grade'] ?? '—' }})</td>
        </tr>
        @endif
    </table>

    <table class="marks">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Breakdown</th>
                <th>Obtained</th>
                <th>Total</th>
                <th>%</th>
                <th>Grade</th>
                <th>GPA</th>
                <th>Highest in exam</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subjects as $s)
            @php
                $bd = is_array($s['marks_breakdown'] ?? null) ? $s['marks_breakdown'] : [];
                $bdParts = [];
                foreach (['mcq' => 'MCQ', 'cq' => 'CQ', 'written' => 'Written', 'practical' => 'Practical'] as $bk => $bl) {
                    if (array_key_exists($bk, $bd) && $bd[$bk] !== null && $bd[$bk] !== '') {
                        $bdParts[] = $bl . ': ' . $bd[$bk];
                    }
                }
            @endphp
            <tr>
                <td>{{ $s['subject'] }}</td>
                <td>{{ count($bdParts) ? implode(' · ', $bdParts) : '—' }}</td>
                <td>{{ $s['marks_obtained'] ?? '—' }}</td>
                <td>{{ $s['total_marks'] }}</td>
                <td>{{ $s['percentage'] !== null ? $s['percentage'].'%' : '—' }}</td>
                <td>{{ $s['grade'] ?? '—' }}</td>
                <td>{{ $s['grade_point'] ?? '—' }}</td>
                <td>
                    {{ $s['highest_in_exam'] ?? '—' }}
                    @if(!empty($s['is_subject_topper'])) <span class="topper">★ Top</span> @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($standing))
    <div class="summary">
        <table>
            <tr>
                <td><div class="val">{{ $standing['total_marks'] }}/{{ $standing['total_possible'] }}</div><div class="lbl">Total Marks</div></td>
                <td><div class="val">{{ $standing['percentage'] }}%</div><div class="lbl">Percentage</div></td>
                <td><div class="val">{{ $standing['gpa'] }}</div><div class="lbl">GPA</div></td>
                <td><div class="val">{{ $standing['overall_grade'] }}</div><div class="lbl">Overall Grade</div></td>
            </tr>
        </table>
        @if(!empty($standing['position']))
        <p class="merit-note">Merit position {{ $standing['position'] }} among {{ $standing['total_students'] }} students ({{ $merit_scope['label'] ?? 'scope' }}).</p>
        @endif
    </div>
    @endif

    <div class="footer">Generated {{ $generated_at }}</div>
</body>
</html>
