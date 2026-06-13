<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merit List - {{ $exam['name'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 3px solid #4f46e5; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 20px; color: #4f46e5; }
        .header p { margin: 4px 0; color: #666; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #4f46e5; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; }
        tr:nth-child(even) { background: #f9fafb; }
        .rank-1 { background: #fef3c7 !important; font-weight: bold; }
        .rank-2 { background: #f3f4f6 !important; }
        .rank-3 { background: #fde68a !important; }
        .footer { margin-top: 16px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Merit List</h1>
        <p>{{ $exam['name'] }} &bull; {{ ucfirst($exam['delivery_channel'] ?? 'offline') }} channel &bull; Ranking: {{ ucfirst($ranking_rule) }} (ties share rank)</p>
        @if(!empty($scope['type']))
        <p>Scope: {{ ucfirst($scope['type']) }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Roll</th>
                <th>Student</th>
                <th>Total Marks</th>
                <th>Percentage</th>
                <th>GPA</th>
                <th>Grade</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse($merit_list as $row)
            <tr class="{{ $row['rank'] <= 3 ? 'rank-' . $row['rank'] : '' }}">
                <td>{{ $row['rank'] }}</td>
                <td>{{ $row['roll_no'] ?? '—' }}</td>
                <td>{{ $row['student_name'] }}</td>
                <td>{{ $row['total_marks'] }}/{{ $row['total_possible'] }}</td>
                <td>{{ $row['percentage'] }}%</td>
                <td>{{ $row['gpa'] }}</td>
                <td>{{ $row['overall_grade'] }}</td>
                <td>{{ $row['passed'] ? 'Pass' : 'Fail' }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;">No results available</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Showing top {{ count($merit_list) }} of {{ $total_students }} students &bull; Generated {{ $generated_at }}
    </div>
</body>
</html>
