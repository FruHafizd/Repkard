<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu {{ $member->member_number }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        .page { position: relative; width: 242.65pt; height: 153.07pt; overflow: hidden; }
        .break { page-break-after: always; }
        .box { position: absolute; left: 8pt; top: 8pt; right: 8pt; bottom: 8pt; border: 0.75pt solid #000; }
        .gym { position: absolute; left: 14pt; top: 14pt; font-size: 9pt; font-weight: bold; }
        .photo { position: absolute; left: 14pt; top: 34pt; width: 66pt; height: 88pt; border: 0.5pt solid #000; }
        .photo img { width: 66pt; height: 88pt; }
        .info { position: absolute; left: 90pt; top: 40pt; width: 140pt; }
        .name { font-size: 10pt; font-weight: bold; }
        .meta { font-size: 7.5pt; margin-top: 4pt; }
        .qr { position: absolute; left: 14pt; top: 14pt; width: 100pt; height: 100pt; }
        .qr img { width: 100pt; height: 100pt; }
        .note { position: absolute; left: 124pt; top: 20pt; width: 104pt; font-size: 7pt; }
        .number { position: absolute; left: 124pt; top: 100pt; width: 104pt; font-size: 7pt; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page break">
        <div class="box"></div>
        <div class="gym">{{ config('app.name') }}</div>
        <div class="photo">@if ($photo)<img src="{{ $photo }}" alt="Foto">@endif</div>
        <div class="info">
            <div class="name">{{ $member->user->name }}</div>
            <div class="meta">No. Member<br><strong>{{ $member->member_number }}</strong></div>
            <div class="meta">Terbit<br>{{ $card->issued_at->format('d M Y') }}</div>
        </div>
    </div>
    <div class="page">
        <div class="box"></div>
        <div class="qr"><img src="{{ $qr }}" alt="QR"></div>
        <div class="note">Kartu ini hanya berlaku untuk pemilik yang namanya tertera.</div>
        <div class="number">{{ $member->member_number }}</div>
    </div>
</body>
</html>
