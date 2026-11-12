{{-- Menerbitkan token dari config/design.php sebagai variabel CSS (--rk-*). Jangan menulis nilai warna di sini. --}}
@php($d = config('design'))
@if ($fonts ?? true)
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="{{ $d['font']['url'] }}" rel="stylesheet">
@endif
<style>
    :root {
        @foreach (['primary', 'success', 'danger'] as $name)
            @foreach ($d[$name] as $shade => $hex)
                --rk-{{ $name }}-{{ $shade }}: {{ $hex }};
            @endforeach
        @endforeach
        @foreach (['accent', 'ink', 'muted', 'line', 'paper', 'surface', 'radius'] as $name)
            --rk-{{ $name }}: {{ $d[$name] }};
        @endforeach
        --rk-font-sans: '{{ $d['font']['sans'] }}', ui-sans-serif, system-ui, sans-serif;
        --rk-font-display: '{{ $d['font']['display'] }}', 'Arial Black', ui-sans-serif, system-ui, sans-serif;
    }
</style>
