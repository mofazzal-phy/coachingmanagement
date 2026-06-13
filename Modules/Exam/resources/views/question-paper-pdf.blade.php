<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Question Paper - {{ $subject }}</title>
    <style>
        @page { margin: 18px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 16px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #4338ca;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            color: #4338ca;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 4px 0 0;
            font-size: 13px;
            font-weight: normal;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10px;
        }
        .meta td {
            padding: 3px 6px;
            border: 1px solid #ddd;
        }
        .meta .label {
            background: #f3f4f6;
            font-weight: bold;
            width: 18%;
        }
        .variant-badge {
            float: right;
            background: #4338ca;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            text-transform: uppercase;
        }
        .question {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .q-head {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }
        .q-num {
            font-weight: bold;
            color: #4338ca;
        }
        .q-marks {
            float: right;
            font-weight: bold;
            color: #666;
        }
        .q-content {
            margin-left: 8px;
            line-height: 1.45;
        }
        .stimulus {
            margin: 6px 0 8px 16px;
            padding: 8px;
            background: #f9fafb;
            border-left: 3px solid #4338ca;
        }
        .options {
            margin: 6px 0 0 20px;
        }
        .option {
            margin: 3px 0;
        }
        .option-key {
            font-weight: bold;
            display: inline-block;
            width: 22px;
        }
        .parts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .parts-table th, .parts-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 10px;
        }
        .parts-table th {
            background: #eef2ff;
        }
        .answer-line {
            margin-top: 6px;
            border-bottom: 1px dotted #999;
            height: 18px;
        }
        .answer-key {
            color: #059669;
            font-weight: bold;
            margin-top: 4px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="variant-badge">{{ str_replace('_', ' ', $variant) }}</span>
        <h1>{{ $exam->name ?? 'Exam' }}</h1>
        <h2>{{ $subject }} @if($subject_code)({{ $subject_code }})@endif</h2>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Exam Type</td>
            <td>{{ $exam->examType->name ?? 'N/A' }}</td>
            <td class="label">Date</td>
            <td>{{ $routine->exam_date ? $routine->exam_date->format('d M, Y') : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Duration</td>
            <td>{{ $duration }} minutes</td>
            <td class="label">Total Marks</td>
            <td>{{ number_format($total_marks, 0) }}</td>
        </tr>
        <tr>
            <td class="label">Time</td>
            <td>{{ $routine->start_time_formatted ?? '--' }} - {{ $routine->end_time_formatted ?? '--' }}</td>
            <td class="label">Pass Marks</td>
            <td>{{ $routine->pass_marks ?? 'N/A' }}</td>
        </tr>
    </table>

    @if($routine->instructions)
        <p><strong>Instructions:</strong> {{ $routine->instructions }}</p>
    @endif

    @php
        $optionLabels = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ'];
    @endphp

    @forelse($questions as $index => $q)
        <div class="question">
            <div class="q-head">
                <span class="q-num">প্রশ্ন {{ $index + 1 }}.</span>
                <span class="q-marks">[{{ number_format($q['marks'], 0) }}]</span>
            </div>
            <div class="q-content">
                @if(!empty($q['stimulus']))
                    <div class="stimulus">{!! nl2br(e($q['stimulus'])) !!}</div>
                @endif

                @if($q['question_type'] === 'mcq')
                    <div>{!! nl2br(e($q['content'])) !!}</div>
                    <div class="options">
                        @foreach(($q['options'] ?? []) as $oi => $opt)
                            <div class="option">
                                <span class="option-key">{{ $optionLabels[$oi] ?? chr(97 + $oi) }})</span>
                                {{ is_array($opt) ? ($opt['text'] ?? json_encode($opt)) : $opt }}
                            </div>
                        @endforeach
                    </div>
                    @if($show_answers && isset($q['correct_answer']['index']))
                        <div class="answer-key">
                            Answer: {{ $optionLabels[$q['correct_answer']['index']] ?? ($q['correct_answer']['index'] + 1) }}
                        </div>
                    @elseif(!$show_answers)
                        <div class="answer-line"></div>
                    @endif
                @elseif($q['question_type'] === 'cq')
                    @if(!empty($q['content']))
                        <div><strong>{{ $q['content'] }}</strong></div>
                    @endif
                    @if(!empty($q['parts']))
                        <table class="parts-table">
                            <thead>
                                <tr>
                                    <th style="width:8%">Part</th>
                                    <th>Question</th>
                                    <th style="width:10%">Marks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($q['parts'] as $pi => $part)
                                    <tr>
                                        <td>{{ chr(97 + $pi) }}</td>
                                        <td>{{ $part['text'] ?? $part['content'] ?? '' }}</td>
                                        <td>{{ $part['marks'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if(!$show_answers)
                        <div class="answer-line"></div>
                        <div class="answer-line"></div>
                    @endif
                @else
                    <div>{!! nl2br(e($q['content'])) !!}</div>
                    @if(!$show_answers)
                        <div class="answer-line"></div>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <p>No questions attached to this routine.</p>
    @endforelse

    <div class="footer">Generated on {{ $generated_at }}</div>
</body>
</html>
