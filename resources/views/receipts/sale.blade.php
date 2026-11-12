@php($d = config('design'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $sale->number }}</title>
    {{-- dompdf, kertas struk 80 mm. Warna dari config/design.php. --}}
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 12pt 12pt; font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: {{ $d['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2pt 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: {{ $d['muted'] }}; }
        .wordmark { font-size: 13pt; font-weight: bold; font-style: italic; }
        .wordmark span { color: {{ $d['accent'] }}; }
        .sep { border-top: 0.5pt dashed {{ $d['line'] }}; margin: 6pt 0; }
        .void { border: 1.5pt solid {{ $d['danger'][600] }}; color: {{ $d['danger'][600] }}; font-weight: bold; font-size: 10pt; letter-spacing: 1.5pt; text-align: center; padding: 4pt; margin: 6pt 0; }
        .total td { font-size: 10pt; font-weight: bold; padding-top: 4pt; }
        .struck { text-decoration: line-through; }
    </style>
</head>
<body>
    <div class="center">
        <div class="wordmark">{{ config('app.name') }}<span>.</span></div>
    </div>

    @if ($sale->isCancelled())
        <div class="void">DIBATALKAN</div>
    @endif

    <div class="sep"></div>
    <table>
        <tr><td class="muted">No.</td><td class="right">{{ $sale->number }}</td></tr>
        <tr><td class="muted">Tanggal</td><td class="right">{{ $sale->sold_at->format('d M Y H:i') }}</td></tr>
        <tr><td class="muted">Kasir</td><td class="right">{{ $sale->recorder->name }}</td></tr>
        @if ($sale->member?->user || $sale->customer_name)
            <tr><td class="muted">Pembeli</td><td class="right">{{ $sale->member?->user?->name ?? $sale->customer_name }}</td></tr>
        @endif
    </table>
    <div class="sep"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr><td colspan="2">{{ $item->product_name }}</td></tr>
            <tr>
                <td class="muted">{{ $item->quantity }} × Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    <div class="sep"></div>

    <table class="total">
        <tr>
            <td>TOTAL</td>
            <td class="right @if ($sale->isCancelled()) struck @endif">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
        </tr>
    </table>
    <table>
        <tr><td class="muted">Metode</td><td class="right">{{ $sale->method->label() }}</td></tr>
        @if ($sale->cash_received !== null)
            <tr><td class="muted">Uang diterima</td><td class="right">Rp {{ number_format($sale->cash_received, 0, ',', '.') }}</td></tr>
            <tr><td class="muted">Kembalian</td><td class="right">Rp {{ number_format($sale->change(), 0, ',', '.') }}</td></tr>
        @endif
    </table>

    <div class="sep"></div>
    <div class="center muted">Terima kasih</div>
</body>
</html>
