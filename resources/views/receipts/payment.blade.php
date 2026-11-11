<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 13px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 2px 0; vertical-align: top; }
        td.label { width: 35%; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p>Kuitansi No. {{ $payment->id }}@if ($payment->isCancelled()) — DIBATALKAN @endif</p>
    <table>
        <tr><td class="label">Tanggal</td><td>{{ $payment->paid_at->format('d M Y H:i') }}</td></tr>
        <tr><td class="label">Member</td><td>{{ $payment->member->user->name }} ({{ $payment->member->member_number ?? '-' }})</td></tr>
        <tr><td class="label">Paket</td><td>{{ $payment->membership?->plan?->name ?? '-' }}</td></tr>
        <tr><td class="label">Jumlah</td><td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td></tr>
        <tr><td class="label">Metode</td><td>{{ $payment->method->label() }}</td></tr>
        <tr><td class="label">Dicatat oleh</td><td>{{ $payment->recorder->name }}</td></tr>
    </table>
</body>
</html>
