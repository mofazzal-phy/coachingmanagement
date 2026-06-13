<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admit Card - {{ $exam['name'] }}</title>
    <style>
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .card {
            border: 3px solid #6366f1;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background: #4f46e5;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
            color: #fff;
            text-align: center;
            padding: 16px 12px;
        }
        .institution {
            font-size: 17px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4px;
            letter-spacing: 1px;
        }
        .admit-badge {
            display: inline-block;
            background: #fbbf24;
            color: #78350f;
            font-weight: bold;
            font-size: 11px;
            padding: 4px 16px;
            border-radius: 20px;
            margin: 8px 0 4px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .exam-name {
            font-size: 14px;
            font-weight: bold;
            margin: 6px 0 0;
        }
        .exam-type {
            font-size: 10px;
            opacity: 0.9;
        }
        .body {
            padding: 14px;
            background: #faf5ff;
        }
        .student-wrap {
            width: 100%;
            border-collapse: collapse;
        }
        .photo {
            width: 100px;
            height: 118px;
            border: 3px solid #a78bfa;
            border-radius: 6px;
            overflow: hidden;
            background: #ede9fe;
        }
        .photo img {
            width: 100px;
            height: 118px;
            object-fit: cover;
        }
        .photo-ph {
            line-height: 118px;
            text-align: center;
            color: #7c3aed;
            font-size: 10px;
            font-weight: bold;
        }
        .details {
            padding-left: 14px;
            vertical-align: top;
        }
        .detail-row {
            margin-bottom: 6px;
            background: #fff;
            border-left: 4px solid #8b5cf6;
            padding: 5px 8px;
            border-radius: 0 4px 4px 0;
        }
        .detail-row .lbl {
            font-size: 8px;
            text-transform: uppercase;
            color: #7c3aed;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .detail-row .val {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 1px;
        }
        .exam-strip {
            margin-top: 12px;
            background: linear-gradient(90deg, #ddd6fe, #fce7f3);
            border: 1px solid #c4b5fd;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .exam-strip .lbl {
            font-size: 9px;
            color: #6d28d9;
            text-transform: uppercase;
            font-weight: bold;
        }
        .exam-strip .val {
            font-size: 13px;
            font-weight: bold;
            color: #4c1d95;
            margin-top: 2px;
        }
        .notes {
            margin-top: 12px;
            background: #fff;
            border: 1px dashed #a78bfa;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 9px;
            color: #5b21b6;
        }
        .notes strong { color: #6d28d9; }
        .notes ul { margin: 4px 0 0; padding-left: 14px; }
        .sigs {
            margin-top: 16px;
            width: 100%;
        }
        .sigs td {
            width: 33%;
            text-align: center;
            font-size: 8px;
            color: #64748b;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 28px;
            padding-top: 3px;
        }
        .footer {
            background: #4f46e5;
            color: #e0e7ff;
            text-align: center;
            font-size: 8px;
            padding: 6px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <p class="institution">{{ $institution['name'] ?? config('app.name') }}</p>
            <div class="admit-badge">Admit Card</div>
            <p class="exam-name">{{ $exam['name'] }}</p>
            @if(!empty($exam['type']))
                <p class="exam-type">{{ $exam['type'] }}</p>
            @endif
            @if(!empty($exam['session']))
                <p class="exam-type">{{ $exam['session'] }}</p>
            @endif
        </div>

        <div class="body">
            <table class="student-wrap">
                <tr>
                    <td style="width:108px;">
                        <div class="photo">
                            @if(!empty($student['photo']))
                                <img src="{{ $student['photo'] }}" alt="" />
                            @else
                                <div class="photo-ph">PHOTO</div>
                            @endif
                        </div>
                    </td>
                    <td class="details">
                        <div class="detail-row">
                            <div class="lbl">Student Name</div>
                            <div class="val">{{ $student['name'] }}</div>
                        </div>
                        <div class="detail-row">
                            <div class="lbl">Student ID</div>
                            <div class="val">{{ $student['student_id'] }}</div>
                        </div>
                        @if(!empty($student['roll_no']) && $student['roll_no'] !== '—')
                        <div class="detail-row">
                            <div class="lbl">Roll No</div>
                            <div class="val">{{ $student['roll_no'] }}</div>
                        </div>
                        @endif
                        <div class="detail-row">
                            <div class="lbl">Batch</div>
                            <div class="val">{{ $student['batch'] }}</div>
                        </div>
                        <div class="detail-row">
                            <div class="lbl">Course</div>
                            <div class="val">{{ $student['course'] }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="exam-strip">
                <div class="lbl">Examination Period</div>
                <div class="val">{{ $exam['start_date'] }}@if($exam['start_date'] !== $exam['end_date']) — {{ $exam['end_date'] }}@endif</div>
            </div>

            <div class="notes">
                <strong>Important:</strong>
                <ul>
                    <li>Bring this admit card and valid ID to the exam hall.</li>
                    <li>Report 15 minutes before exam time.</li>
                    <li>See your exam routine on the student portal for subject-wise schedule.</li>
                </ul>
            </div>

            <table class="sigs">
                <tr>
                    <td><div class="sig-line">Exam Controller</div></td>
                    <td><div class="sig-line">Principal</div></td>
                    <td><div class="sig-line">Student</div></td>
                </tr>
            </table>
        </div>

        <div class="footer">Generated {{ $generated_at }} · Valid for {{ $exam['name'] }} only</div>
    </div>
</body>
</html>
