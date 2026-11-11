import { Html5QrcodeScanner } from 'html5-qrcode';

/**
 * Pasang pemindai kamera (+ pilih file gambar) pada elemen, lalu panggil onScan(teks).
 * Setelah terbaca, scan berikutnya ditunda 2 detik agar tidak terhitung dobel.
 */
window.startScanner = (elementId, onScan) => {
    let locked = false;

    const scanner = new Html5QrcodeScanner(
        elementId,
        { fps: 10, qrbox: 250, rememberLastUsedCamera: true },
        false,
    );

    scanner.render(
        (decodedText) => {
            if (locked) {
                return;
            }

            locked = true;
            onScan(decodedText);
            setTimeout(() => {
                locked = false;
            }, 2000);
        },
        () => {},
    );
};
