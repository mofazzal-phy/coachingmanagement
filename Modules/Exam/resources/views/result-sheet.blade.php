<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Result Sheet - {{ $exam['name'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 18px; color: #4f46e5; }
        .header p { margin: 3px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #4f46e5; color: white; padding: 5px 6px; text-align: left; font-size: 9px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; }
        tr:nth-child(even) { background: #f9fafb; }
        .footer { margin-top: 12px; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Result Sheet</h1>
        <p>{{ $exam['name'] }} &bull; {{ $exam['level_name'] }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Roll</th>
                <th>Student</th>
                <th>Marks</th>
                <th>%</th>
                <th>GPA</th>
                <th>Grade</th>
                <th>Pass</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>{{ $row['rank'] }}</td>
                <td>{{ $row['roll_no'] ?? '—' }}</td>
                <td>{{ $row['student_name'] }}</td>
                <td>{{ $row['total_marks'] }}/{{ $row['total_possible'] }}</td>
                <td>{{ $row['percentage'] }}%</td>
                <td>{{ $row['gpa'] }}</td>
                <td>{{ $row['overall_grade'] }}</td>
                <td>{{ $row['passed'] }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;">No results</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated {{ $generated_at }}</div>
</body>
</html>
