<?php

/*
 * Token desain Repkard — SATU-SATUNYA tempat nilai warna, font, dan radius.
 * Dipakai oleh: kedua panel Filament (PanelProvider), landing page & CSS kustom
 * (lewat resources/views/partials/design-tokens.blade.php), serta kartu & kuitansi PDF.
 * Penjelasan tiap token: docs/design-tokens.md.
 */

return [
    // Biru kobalt. Shade 600 = warna brand utama (tombol, tautan, hero).
    'primary' => [
        50 => '#EEF2FF',
        100 => '#DEE5FF',
        200 => '#C0CCFF',
        300 => '#97AAFD',
        400 => '#6A82F6',
        500 => '#4460EC',
        600 => '#1F3FD6',
        700 => '#1932B0',
        800 => '#16298A',
        900 => '#14246C',
        950 => '#0D1640',
    ],

    // Hijau "diterima / aktif / lunas". Shade 600 dipakai sebagai latar penuh di halaman scan.
    'success' => [
        50 => '#ECFDF3',
        100 => '#D1FADF',
        200 => '#A6F0C0',
        300 => '#6DDF99',
        400 => '#36C574',
        500 => '#129250',
        600 => '#0B7A40',
        700 => '#0A6636',
        800 => '#0B512D',
        900 => '#0A4327',
        950 => '#042513',
    ],

    // Merah "ditolak / habis / dibatalkan".
    'danger' => [
        50 => '#FEF2F2',
        100 => '#FDE3E4',
        200 => '#FBCACD',
        300 => '#F7A1A6',
        400 => '#F06A73',
        500 => '#E33D49',
        600 => '#D01F2E',
        700 => '#AE1725',
        800 => '#901722',
        900 => '#781922',
        950 => '#41080D',
    ],

    // Jingga aksen: tombol ajakan di landing, titik wordmark, garis kartu. Teks di atasnya memakai 'ink'.
    'accent' => '#FF6A13',

    // Netral.
    'ink' => '#0E1326',     // teks utama, latar gelap
    'muted' => '#535B73',   // teks sekunder
    'line' => '#D5D9E3',    // garis & border
    'paper' => '#F6F7F9',   // latar halaman
    'surface' => '#FFFFFF', // latar kartu/panel

    'font' => [
        'sans' => 'Public Sans',  // teks
        'display' => 'Archivo',   // judul (800, miring untuk judul besar)
        // Satu URL Bunny Fonts untuk kedua keluarga font.
        'url' => 'https://fonts.bunny.net/css?family=archivo:800,800i|public-sans:400,500,600,700&display=swap',
    ],

    // Radius dasar (tombol, input). Kartu/section = radius + 2px.
    'radius' => '6px',
];
