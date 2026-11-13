{{-- Logo Repkard. Varian 'white' untuk latar gelap/biru. Tinggi diatur lewat .rk-logo (resources/css/tokens.css). --}}
<img
    src="{{ asset(($variant ?? 'color') === 'white' ? 'images/brand/logo-white.png' : 'images/brand/logo.png') }}"
    alt="{{ config('app.name') }}"
    class="rk-logo"
    width="834"
    height="128"
>
