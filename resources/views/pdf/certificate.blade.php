<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Chứng nhận {{ $number }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #0f172a; }
        .sheet { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fffdf7; }
        .frame { position: absolute; top: 22px; left: 22px; right: 22px; bottom: 22px; border: 3px solid #0b1f3a; }
        .inner { position: absolute; top: 32px; left: 32px; right: 32px; bottom: 32px; border: 1px solid #f59e0b; }
        .band { position: absolute; top: 33px; left: 33px; width: 14px; bottom: 33px; background: #0b1f3a; }
        .content { position: absolute; top: 120px; left: 110px; width: 922px; text-align: center; }
        .brand { font-size: 15px; letter-spacing: 3px; color: #0b1f3a; font-weight: bold; }
        .brand span { color: #f59e0b; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 44px; margin: 30px 0 8px; color: #0b1f3a; }
        .lead { font-size: 14px; color: #475569; margin: 0; }
        .name { font-family: 'DejaVu Serif', serif; font-size: 38px; font-weight: bold; margin: 34px 0 10px; }
        .rule { width: 340px; margin: 0 auto; border-top: 1px solid #f59e0b; }
        .course { font-size: 19px; margin: 22px 0 0; }
        .course b { color: #0b1f3a; }
        .foot { position: absolute; left: 110px; top: 560px; width: 922px; }
        .foot td { vertical-align: bottom; font-size: 11px; color: #475569; }
        .foot .label { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #94a3b8; }
        .foot .value { font-size: 13px; color: #0f172a; font-weight: bold; }
        .qr { width: 96px; height: 96px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="frame"></div>
    <div class="inner"></div>
    <div class="band"></div>
    <div class="content">
        <div class="brand">EDU<span>NEST</span></div>
        <h1>Chứng nhận hoàn thành</h1>
        <p class="lead">EduNest chứng nhận học viên</p>
        <p class="name">{{ $holder }}</p>
        <div class="rule"></div>
        <p class="course">đã hoàn thành khóa học <b>{{ $course }}</b></p>
    </div>
    <table class="foot" cellspacing="0" cellpadding="0">
        <tr>
            <td width="34%">
                <div class="label">Ngày cấp</div>
                <div class="value">{{ $issuedOn }}</div>
            </td>
            <td width="40%">
                <div class="label">Mã chứng nhận</div>
                <div class="value">{{ $number }}</div>
                <div style="margin-top:4px">Tra cứu: {{ $url }}</div>
            </td>
            <td width="26%" align="right">
                <img class="qr" alt="Mã QR xác minh" src="data:image/svg+xml;base64,{{ $qr }}">
            </td>
        </tr>
    </table>
</div>
</body>
</html>
