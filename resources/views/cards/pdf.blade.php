@php($d = config('design'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu {{ $member->member_number }}</title>
    {{-- dompdf: tanpa flex/grid; semua posisi absolut dalam pt. Kartu CR80 = 242.65 × 153.07 pt.
         Warna dari config/design.php. Pita berwarna tetap terbaca bila dicetak hitam-putih (teks putih di atas gelap). --}}
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: {{ $d['ink'] }}; }
        .page { position: relative; width: 242.65pt; height: 153.07pt; overflow: hidden; background: #fff; }
        .break { page-break-after: always; }

        .band { position: absolute; left: 0; top: 0; width: 242.65pt; height: 30pt; background: {{ $d['primary'][600] }}; }
        .band-foot { position: absolute; left: 0; top: 125pt; width: 242.65pt; height: 28.07pt; background: {{ $d['primary'][600] }}; }
        .bar { position: absolute; left: 0; top: 147.5pt; width: 242.65pt; height: 5.57pt; background: {{ $d['accent'] }}; }
        .wordmark { position: absolute; left: 12pt; font-size: 12pt; font-weight: bold; font-style: italic; color: #fff; }
        .wordmark span { color: {{ $d['accent'] }}; }
        .kind { position: absolute; right: 12pt; top: 11pt; font-size: 5.5pt; font-weight: bold; letter-spacing: 1pt; color: #fff; }

        .photo { position: absolute; left: 12pt; top: 40pt; width: 72pt; height: 96pt; border: 0.75pt solid {{ $d['ink'] }}; }
        .photo img { width: 72pt; height: 96pt; }
        .info { position: absolute; left: 96pt; top: 42pt; width: 134pt; }
        .name { font-size: 11pt; font-weight: bold; line-height: 1.15; }
        .label { font-size: 5pt; font-weight: bold; letter-spacing: 0.8pt; color: {{ $d['muted'] }}; margin-top: 8pt; }
        .value { font-size: 9.5pt; font-weight: bold; }
        .value-sm { font-size: 7.5pt; }

        .qr { position: absolute; left: 12pt; top: 10pt; width: 106pt; height: 106pt; }
        .qr img { width: 106pt; height: 106pt; }
        .note { position: absolute; left: 128pt; top: 14pt; width: 102pt; }
        .note-title { font-size: 7.5pt; font-weight: bold; line-height: 1.2; }
        .note-text { font-size: 6pt; line-height: 1.35; margin-top: 4pt; color: {{ $d['muted'] }}; }
        .number { position: absolute; left: 128pt; top: 94pt; width: 102pt; }
    </style>
</head>
<body>
    {{-- Depan --}}
    <div class="page break">
        <div class="band"></div>
        <div class="wordmark" style="top: 7pt">{{ config('app.name') }}<span>.</span></div>
        <div class="kind">KARTU MEMBER</div>

        <div class="photo">@if ($photo)<img src="{{ $photo }}" alt="Foto">@endif</div>
        <div class="info">
            <div class="name">{{ $member->user->name }}</div>
            <div class="label">NO. MEMBER</div>
            <div class="value">{{ $member->member_number }}</div>
            <div class="label">TERBIT</div>
            <div class="value value-sm">{{ $card->issued_at->format('d M Y') }}</div>
        </div>

        <div class="bar"></div>
    </div>

    {{-- Belakang --}}
    <div class="page">
        <div class="qr"><img src="{{ $qr }}" alt="QR"></div>
        <div class="note">
            <div class="note-title">Hanya untuk pemilik kartu</div>
            <div class="note-text">Kartu ini hanya berlaku untuk pemilik yang namanya tertera. Tunjukkan QR ke petugas setiap datang.</div>
        </div>
        <div class="number">
            <div class="label" style="margin-top: 0">NO. MEMBER</div>
            <div class="value">{{ $member->member_number }}</div>
        </div>

        <div class="band-foot"></div>
        <div class="wordmark" style="top: 130pt; font-size: 10pt">{{ config('app.name') }}<span>.</span></div>
    </div>
</body>
</html>
