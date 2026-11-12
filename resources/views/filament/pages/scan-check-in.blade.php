<x-filament-panels::page>
    @vite('resources/js/scanner.js')

    @php($result = $this->currentResult())

    <div class="rk-scan">
        {{-- Hasil: selalu di tempat yang sama supaya mata pegawai tidak perlu mencari. --}}
        <div class="rk-scan-result" role="status" aria-live="assertive">
            @if ($rejectedReason)
                <section class="rk-scan-panel rk-scan-no">
                    <header class="rk-scan-status">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10.5" stroke-width="2"/><path d="M8 8l8 8m0-8l-8 8"/></svg>
                        <h2>Ditolak</h2>
                    </header>
                    <p class="rk-scan-reason">{{ $rejectedReason }}</p>
                    <p class="rk-scan-hint">Member belum boleh masuk. Pindai kartu berikutnya untuk melanjutkan.</p>
                </section>
            @elseif ($result?->allowed)
                @php($member = $result->member)
                @php($membership = $result->membership)
                <section class="rk-scan-panel rk-scan-ok">
                    <header class="rk-scan-status">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10.5" stroke-width="2"/><path d="M7 12.5l3.4 3.4L17 8.8"/></svg>
                        <h2>Diterima</h2>
                    </header>
                    <p class="rk-scan-name">{{ $member->user?->name }}</p>
                    <p class="rk-scan-hint">Cocokkan wajah orang di depan Anda dengan foto ini.</p>

                    <div class="rk-scan-body">
                        @if ($member->photoUrl())
                            <img class="rk-scan-photo" src="{{ $member->photoUrl() }}" alt="Foto member" width="360" height="480">
                        @else
                            <div class="rk-scan-photo rk-scan-photo-empty">Belum ada foto</div>
                        @endif

                        <div class="rk-scan-side">
                            <dl class="rk-scan-facts">
                                <div><dt>Nomor member</dt><dd>{{ $member->member_number }}</dd></div>
                                <div><dt>Paket</dt><dd>{{ $membership->plan?->name }}</dd></div>
                                <div><dt>Berlaku sampai</dt><dd>{{ $membership->ends_on->translatedFormat('d M Y') }}</dd></div>
                                <div><dt>Sisa hari</dt><dd>{{ (int) now()->startOfDay()->diffInDays($membership->ends_on, false) }}</dd></div>
                                <div><dt>Sisa kuota</dt><dd>{{ $membership->visits_remaining ?? 'Tanpa batas' }}</dd></div>
                            </dl>

                            <div class="rk-scan-actions">
                                <button type="button" class="rk-scan-btn rk-scan-btn-yes" wire:click="konfirmasi" wire:loading.attr="disabled">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                    Wajah cocok — Check-in
                                </button>
                                <button type="button" class="rk-scan-btn rk-scan-btn-no" wire:click="tolakWajah" wire:loading.attr="disabled">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6.5 6.5l11 11m0-11l-11 11"/></svg>
                                    Wajah tidak cocok — Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            @else
                <section class="rk-scan-panel rk-scan-idle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 8V5a1 1 0 011-1h3M16 4h3a1 1 0 011 1v3M20 16v3a1 1 0 01-1 1h-3M8 20H5a1 1 0 01-1-1v-3M8 9h3v3H8zM13 9h3M13 12v3h3M8 15h2"/></svg>
                    <p class="rk-scan-idle-title">Siap memindai</p>
                    <p>Arahkan kartu atau QR member ke kamera. Hasilnya muncul di sini.</p>
                </section>
            @endif
        </div>

        <div class="rk-scan-reader">
            <x-filament::section heading="Pindai kartu">
                <div wire:ignore id="reader" x-data x-init="window.startScanner('reader', (text) => $wire.scan(text))"></div>

                <div style="margin-top: 1rem">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            autofocus
                            placeholder="Scanner USB: arahkan kartu, hasil otomatis terisi"
                            aria-label="Hasil scanner USB"
                            autocomplete="off"
                            x-on:keydown.enter.prevent="$wire.scan($event.target.value); $event.target.value = ''"
                        />
                    </x-filament::input.wrapper>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
