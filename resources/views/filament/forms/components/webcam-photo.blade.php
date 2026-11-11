<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
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
        <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400">
            Kamera tidak tersedia, silakan unggah foto.
        </p>

        <img x-show="state" x-cloak :src="state" alt="Hasil foto" class="w-48 rounded-lg" />

        <video
            x-ref="video"
            x-show="stream"
            x-cloak
            autoplay
            playsinline
            muted
            class="w-48 rounded-lg"
        ></video>
        <canvas x-ref="canvas" class="hidden"></canvas>

        <div class="flex gap-2">
            <x-filament::button type="button" color="gray" size="sm" x-show="! stream && ! state" x-on:click="start()">
                Nyalakan kamera
            </x-filament::button>
            <x-filament::button type="button" size="sm" x-show="stream" x-cloak x-on:click="capture()">
                Ambil foto
            </x-filament::button>
            <x-filament::button type="button" color="gray" size="sm" x-show="state" x-cloak x-on:click="retake()">
                Ulangi
            </x-filament::button>
        </div>
    </div>
</x-dynamic-component>
