<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Teacher ID Card</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; }
        .card {
            width: 242.65pt;
            height: 153.07pt;
            border: 2px solid #059669;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background: #059669;
            color: #fff;
            text-align: center;
            padding: 6px 8px;
        }
        .institution { font-size: 9px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .type-badge { font-size: 7px; letter-spacing: 1px; margin-top: 2px; }
        .body { padding: 8px; }
        .row { width: 100%; border-collapse: collapse; }
        .photo {
            width: 52px; height: 62px;
            border: 2px solid #a7f3d0;
            border-radius: 4px;
            background: #ecfdf5;
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
            color: #059669;
            font-weight: bold;
        }
        .photo img { width: 52px; height: 62px; object-fit: cover; }
        .name { font-size: 11px; font-weight: bold; color: #1e293b; margin: 0 0 3px; }
        .meta { font-size: 8px; color: #475569; line-height: 1.35; }
        .id-no { font-size: 9px; font-weight: bold; color: #059669; margin-top: 4px; }
        .qr { width: 52px; height: 52px; }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding: 4px 8px;
            font-size: 6px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <p class="institution">{{ $institution['name'] }}</p>
            <div class="type-badge">{{ $person['type'] }} ID CARD</div>
        </div>
        <div class="body">
            <table class="row">
                <tr>
                    <td style="width: 58px; vertical-align: top;">
                        <div class="photo">
                            @if($person['photo_url'])
                                <img src="{{ $person['photo_url'] }}" alt="Photo">
                            @else
                                {{ strtoupper(substr($person['name'], 0, 1)) }}
                            @endif
                        </div>
                    </td>
                    <td style="vertical-align: top; padding-left: 6px;">
                        <p class="name">{{ $person['name'] }}</p>
                        <div class="meta">
                            @if($person['class'])<div>Subject: {{ $person['class'] }}</div>@endif
                            @if($person['batch'])<div>Group: {{ $person['batch'] }}</div>@endif
                            @if($person['phone'])<div>Phone: {{ $person['phone'] }}</div>@endif
                        </div>
                        <div class="id-no">{{ $person['id_label'] }}: {{ $person['id_number'] }}</div>
                    </td>
                    <td style="width: 56px; vertical-align: top; text-align: right;">
                        @if($qr_base64)
                            <img class="qr" src="{{ $qr_base64 }}" alt="QR">
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        <div class="footer">Generated {{ $generated_at }}</div>
    </div>
</body>
</html>
