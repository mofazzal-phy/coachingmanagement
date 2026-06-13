<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exam Routine - {{ $exam['name'] }}</title>
    <style>
        @page {
            margin: 20px;
            padding: 0;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }
        .header p {
            margin: 3px 0;
            font-size: 10px;
            color: #999;
        }
        .exam-info {
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f8f9ff;
            border-radius: 5px;
            border-left: 4px solid #4f46e5;
        }
        .exam-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .exam-info td {
            padding: 3px 8px;
            font-size: 10px;
        }
        .exam-info .label {
            font-weight: bold;
            color: #666;
            width: 100px;
        }
        .exam-info .value {
            color: #333;
        }
        .day-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .day-header {
            background: #4f46e5;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }
        table.schedule {
            width: 100%;
            border-collapse: collapse;
        }
        table.schedule th {
            background: #eef2ff;
            color: #4f46e5;
            padding: 6px 10px;
            font-size: 10px;
            text-align: left;
            text-transform: uppercase;
            border-bottom: 2px solid #4f46e5;
        }
        table.schedule td {
            padding: 5px 10px;
            font-size: 10px;
            border-bottom: 1px solid #eee;
        }
        table.schedule tr:nth-child(even) td {
            background: #fafafa;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-size: 9px;
            color: #999;
            text-align: center;
        }
        .summary {
            margin-top: 15px;
            padding: 10px 15px;
            background: #f0fdf4;
            border-radius: 5px;
            border-left: 4px solid #059669;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 3px 8px;
            font-size: 10px;
        }
        .summary .label {
            font-weight: bold;
            color: #666;
            width: 120px;
        }
        .summary .value {
            color: #333;
            font-weight: bold;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 50px;
            color: rgba(79, 70, 229, 0.04);
            font-weight: bold;
            text-transform: uppercase;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="watermark">EXAM ROUTINE</div>

    <!-- Header -->
    <div class="header">
        <h1>{{ config('app.name', 'Institution Name') }}</h1>
        <h2>Exam Routine</h2>
        <p>Generated on: {{ $generated_at }}</p>
    </div>

    <!-- Exam Info -->
    <div class="exam-info">
        <table>
            <tr>
                <td class="label">Exam Name</td>
                <td class="value">: {{ $exam['name'] }}</td>
                <td class="label">Exam Type</td>
                <td class="value">: {{ $exam['type'] }}</td>
            </tr>
            <tr>
                <td class="label">Level</td>
                <td class="value">: {{ $exam['level'] }} ({{ $exam['level_name'] }})</td>
                <td class="label">Duration</td>
                <td class="value">: {{ $exam['start_date'] }} - {{ $exam['end_date'] }}</td>
            </tr>
        </table>
    </div>

    <!-- Daily Schedule -->
    @foreach($grouped as $day)
    <div class="day-section">
        <div class="day-header">{{ $day['date'] }} ({{ count($day['routines']) }} subject{{ count($day['routines']) > 1 ? 's' : '' }})</div>
        <table class="schedule">
            <thead>
                <tr>
                    <th style="width:5%;">#</th>
                    <th style="width:25%;">Subject</th>
                    <th style="width:15%;">Code</th>
                    <th style="width:20%;">Time</th>
                    <th style="width:15%;">Room</th>
                    <th style="width:20%;">Teacher</th>
                </tr>
            </thead>
            <tbody>
                @foreach($day['routines'] as $index => $routine)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $routine['subject'] }}</td>
                    <td>{{ $routine['subject_code'] ?: 'N/A' }}</td>
                    <td>{{ $routine['start_time'] }} - {{ $routine['end_time'] }}</td>
                    <td>{{ $routine['room'] }}</td>
                    <td>{{ $routine['teacher'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    <!-- Summary -->
    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Exam Days</td>
                <td class="value">: {{ $total_days }}</td>
                <td class="label">Total Subjects</td>
                <td class="value">: {{ $total_subjects }}</td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'Institution Name') }}. All rights reserved.</p>
    </div>
</body>
</html>
