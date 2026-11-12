@php($d = config('design'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->id }}</title>
    {{-- dompdf, kertas A6 (105 × 148 mm): tata letak memakai tabel. Warna dari config/design.php. --}}
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: {{ $d['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }

        .head { background: {{ $d['primary'][600] }}; color: #fff; }
        .head td { padding: 14pt 18pt; vertical-align: middle; }
        .wordmark { font-size: 15pt; font-weight: bold; font-style: italic; }
        .wordmark span { color: {{ $d['accent'] }}; }
        .title { text-align: right; font-size: 9pt; font-weight: bold; letter-spacing: 1.5pt; }
        .bar { height: 4pt; background: {{ $d['accent'] }}; }

        .body { padding: 16pt 18pt 0 18pt; }
        .meta td { padding: 0 0 12pt 0; }
        .label { font-size: 6pt; font-weight: bold; letter-spacing: 0.8pt; color: {{ $d['muted'] }}; }
        .big { font-size: 10pt; font-weight: bold; }

        .void { border: 1.5pt solid {{ $d['danger'][600] }}; color: {{ $d['danger'][600] }}; font-weight: bold; font-size: 10pt; letter-spacing: 1.5pt; text-align: center; padding: 5pt; margin-bottom: 12pt; }

        .rows td { padding: 6pt 0; border-bottom: 0.5pt solid {{ $d['line'] }}; }
        .rows td.k { width: 36%; color: {{ $d['muted'] }}; }
        .rows td.v { font-weight: bold; text-align: right; }

        .total td { padding: 10pt 0 0 0; }
        .total .k { font-size: 6pt; font-weight: bold; letter-spacing: 0.8pt; color: {{ $d['muted'] }}; vertical-align: bottom; }
        .total .v { text-align: right; font-size: 17pt; font-weight: bold; }
        .total .struck { text-decoration: line-through; }

        .foot { position: absolute; left: 18pt; right: 18pt; bottom: 16pt; font-size: 6.5pt; color: {{ $d['muted'] }}; border-top: 0.5pt solid {{ $d['line'] }}; padding-top: 6pt; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><span class="wordmark">{{ config('app.name') }}<span>.</span></span></td>
            <td class="title">KUITANSI</td>
        </tr>
    </table>
    <div class="bar"></div>

    <div class="body">
        @if ($payment->isCancelled())
            <div class="void">DIBATALKAN</div>
        @endif

        <table class="meta">
            <tr>
                <td>
                    <div class="label">NO. KUITANSI</div>
                    <div class="big">{{ $payment->id }}</div>
                </td>
                <td style="text-align: right">
                    <div class="label">TANGGAL</div>
                    <div class="big">{{ $payment->paid_at->format('d M Y H:i') }}</div>
                </td>
            </tr>
        </table>

        <table class="rows">
            <tr><td class="k">Member</td><td class="v">{{ $payment->member->user->name }}</td></tr>
            <tr><td class="k">No. member</td><td class="v">{{ $payment->member->member_number ?? '-' }}</td></tr>
            <tr><td class="k">Paket</td><td class="v">{{ $payment->membership?->plan?->name ?? '-' }}</td></tr>
            <tr><td class="k">Metode</td><td class="v">{{ $payment->method->label() }}</td></tr>
            @if ($payment->discount_amount > 0)
                <tr><td class="k">Harga asli</td><td class="v">Rp {{ number_format($payment->original_amount ?? $payment->amount, 0, ',', '.') }}</td></tr>
                <tr><td class="k">Diskon @if ($payment->promoCode) ({{ $payment->promoCode->code }}) @endif</td><td class="v">- Rp {{ number_format($payment->discount_amount, 0, ',', '.') }}</td></tr>
            @endif
            <tr><td class="k">Dicatat oleh</td><td class="v">{{ $payment->recorder->name }}</td></tr>
        </table>

        <table class="total">
            <tr>
                <td class="k">JUMLAH DIBAYAR</td>
                <td class="v @if ($payment->isCancelled()) struck @endif">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="foot">Simpan kuitansi ini sebagai bukti pembayaran.</div>
</body>
</html>
