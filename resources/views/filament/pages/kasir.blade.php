<x-filament-panels::page>
    @php($lines = $this->lines())
    @php($total = $this->total())
    @php($change = $this->change())

    <div class="rk-pos">
        <section class="rk-pos-products" aria-label="Produk">
            <input
                type="search"
                wire:model.live.debounce.250ms="search"
                placeholder="Cari produk atau SKU"
                class="rk-pos-search"
                aria-label="Cari produk"
            >

            <div class="rk-pos-grid">
                @forelse ($this->products() as $product)
                    @php($habis = $product->isOutOfStock())
                    <button
                        type="button"
                        class="rk-pos-tile"
                        wire:click="add({{ $product->id }})"
                        @disabled($habis)
                    >
                        <span class="rk-pos-tile-name">{{ $product->name }}</span>
                        <span class="rk-pos-tile-price">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                        <span class="rk-pos-tile-stock {{ $habis ? 'rk-pos-out' : '' }}">
                            @if (! $product->track_stock)
                                Tanpa stok
                            @elseif ($habis)
                                Stok habis
                            @else
                                Sisa {{ $product->stock }}
                            @endif
                        </span>
                    </button>
                @empty
                    <p class="rk-pos-empty">Tidak ada produk.</p>
                @endforelse
            </div>
        </section>

        <section class="rk-pos-cart" aria-label="Keranjang">
            <h2 class="rk-pos-title">Keranjang</h2>

            @if ($lines->isEmpty())
                <p class="rk-pos-empty">Keranjang masih kosong.</p>
            @else
                <ul class="rk-pos-lines">
                    @foreach ($lines as $line)
                        <li class="rk-pos-line" wire:key="line-{{ $line['product']->id }}">
                            <div class="rk-pos-line-info">
                                <span class="rk-pos-line-name">{{ $line['product']->name }}</span>
                                <span class="rk-pos-line-sub">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</span>
                            </div>
                            <div class="rk-pos-qty">
                                <button type="button" wire:click="decrease({{ $line['product']->id }})" aria-label="Kurangi {{ $line['product']->name }}">−</button>
                                <span>{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="add({{ $line['product']->id }})" aria-label="Tambah {{ $line['product']->name }}">+</button>
                                <button type="button" wire:click="remove({{ $line['product']->id }})" aria-label="Hapus {{ $line['product']->name }}">×</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="rk-pos-total">
                <span>Total</span>
                <strong data-testid="kasir-total">Rp {{ number_format($total, 0, ',', '.') }}</strong>
            </div>

            {{ $this->form }}

            @if ($change !== null)
                <div class="rk-pos-total">
                    <span>Kembalian</span>
                    <strong data-testid="kasir-kembalian" class="{{ $change < 0 ? 'rk-pos-out' : '' }}">
                        {{ $change < 0 ? 'Kurang Rp '.number_format(-$change, 0, ',', '.') : 'Rp '.number_format($change, 0, ',', '.') }}
                    </strong>
                </div>
            @endif

            <x-filament::button
                wire:click="bayar"
                size="xl"
                class="rk-pos-pay"
                :disabled="$lines->isEmpty()"
            >
                Bayar
            </x-filament::button>

            @if ($sale = $this->lastSale())
                <a href="{{ route('kasir.receipt', $sale) }}" target="_blank" class="rk-pos-receipt">
                    Cetak struk {{ $sale->number }}
                </a>
            @endif
        </section>
    </div>
</x-filament-panels::page>
