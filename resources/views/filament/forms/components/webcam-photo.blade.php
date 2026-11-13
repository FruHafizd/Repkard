<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            stream: null,
            error: false,
            async start() {
                this.error = false
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
                    this.$refs.video.srcObject = this.stream
                } catch (e) {
                    this.stop()
                    this.error = true
                }
            },
            stop() {
                if (this.stream) {
                    this.stream.getTracks().forEach((track) => track.stop())
                }
                this.stream = null
                if (this.$refs.video) {
                    this.$refs.video.srcObject = null
                }
            },
            capture() {
                const video = this.$refs.video
                const canvas = this.$refs.canvas
                const w = video.videoWidth
                const h = video.videoHeight
                if (! w || ! h) return
                // Crop tengah rasio 3:4.
                let sw = w
                let sh = (w * 4) / 3
                if (sh > h) {
                    sh = h
                    sw = (h * 3) / 4
                }
                canvas.width = 600
                canvas.height = 800
                canvas.getContext('2d').drawImage(video, (w - sw) / 2, (h - sh) / 2, sw, sh, 0, 0, 600, 800)
                this.state = canvas.toDataURL('image/jpeg', 0.85)
                this.stop()
            },
            destroy() {
                this.stop()
            },
            retake() {
                this.state = null
                this.start()
            },
        }"
        wire:ignore
        class="space-y-2"
    >
        <p x-show="error" x-cloak role="alert" class="text-sm font-medium text-danger-600 dark:text-danger-400">
            Kamera tidak tersedia, silakan unggah foto.
        </p>

        {{-- Bingkai pratinjau rasio pas foto 3:4; ukurannya tetap supaya form tidak melompat. --}}
        <div class="relative flex aspect-[3/4] w-full max-w-60 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-gray-400 dark:border-white/20 dark:bg-white/5">
            <svg x-show="! stream && ! state" viewBox="0 0 60 70" fill="currentColor" class="w-1/2" aria-hidden="true"><circle cx="30" cy="24" r="14"/><path d="M4 70c0-17 11-26 26-26s26 9 26 26z"/></svg>
            <img x-show="state" x-cloak :src="state" alt="Hasil foto" class="absolute inset-0 size-full object-cover" />
            <video
                x-ref="video"
                x-show="stream"
                x-cloak
                autoplay
                playsinline
                muted
                class="absolute inset-0 size-full object-cover"
            ></video>
        </div>
        <canvas x-ref="canvas" class="hidden"></canvas>

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="button" color="gray" icon="heroicon-o-video-camera" x-show="! stream && ! state" x-on:click="start()">
                Nyalakan kamera
            </x-filament::button>
            <x-filament::button type="button" icon="heroicon-o-camera" x-show="stream" x-cloak x-on:click="capture()">
                Ambil foto
            </x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" x-show="state" x-cloak x-on:click="retake()">
                Ulangi
            </x-filament::button>
        </div>
    </div>
</x-dynamic-component>
