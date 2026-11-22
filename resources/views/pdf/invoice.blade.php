<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hóa đơn {{ $number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #0f172a; }
        .brand { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #0b1f3a; }
        .brand span { color: #f59e0b; }
        h1 { font-size: 20px; margin: 14px 0 2px; color: #0b1f3a; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; }
        .items { margin-top: 18px; }
        .items th { text-align: left; background: #0b1f3a; color: #fff; padding: 8px; font-size: 11px; }
        .items td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .num, .items th.num { text-align: right; white-space: nowrap; }
        .sum td { padding: 4px 8px; }
        .total td { font-size: 14px; font-weight: bold; border-top: 2px solid #0b1f3a; padding-top: 8px; }
        .note { margin-top: 24px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
<div class="brand">EDU<span>NEST</span></div>
<h1>Hóa đơn điện tử</h1>
<table class="meta">
    <tr><td class="muted" width="22%">Số hóa đơn</td><td><b>{{ $number }}</b></td></tr>
    <tr><td class="muted">Ngày xuất</td><td>{{ $issuedAt }}</td></tr>
    <tr><td class="muted">Mã đơn</td><td>{{ $orderCode }}</td></tr>
    <tr><td class="muted">Khách hàng</td><td>{{ $customer }} ({{ $email }})</td></tr>
</table>
<table class="items">
    <thead><tr><th>Khóa học</th><th>Loại</th><th class="num">Thành tiền</th></tr></thead>
    <tbody>
    @foreach ($items as $item)
        <tr><td>{{ $item['title'] }}</td><td>{{ $item['kind'] }}</td><td class="num">{{ $item['amount'] }}</td></tr>
    @endforeach
    </tbody>
</table>
<table class="sum" style="margin-top:10px; width: 50%; margin-left: 50%;">
    <tr><td class="muted">Tạm tính</td><td class="num">{{ $subtotal }}</td></tr>
    <tr><td class="muted">Giảm giá</td><td class="num">{{ $discount }}</td></tr>
    <tr class="total"><td>Tổng thanh toán</td><td class="num">{{ $total }}</td></tr>
</table>
<p class="note">{{ $note }}</p>
</body>
</html>
