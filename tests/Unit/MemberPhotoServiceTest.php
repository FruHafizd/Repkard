<?php

namespace Tests\Unit;

use App\Services\MemberPhotoService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberPhotoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function jpegDataUrl(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(300, 300));

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    public function test_data_url_jpeg_valid_tersimpan_600x800(): void
    {
        $path = app(MemberPhotoService::class)->storeFromDataUrl($this->jpegDataUrl());

        Storage::disk('local')->assertExists($path);
        $this->assertSame([600, 800], array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2));
    }

    public function test_prefix_selain_gambar_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        app(MemberPhotoService::class)->storeFromDataUrl('data:text/html;base64,'.base64_encode('<script>1</script>'));
    }

    public function test_base64_rusak_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        app(MemberPhotoService::class)->storeFromDataUrl('data:image/jpeg;base64,@@@bukan-base64@@@');
    }

    public function test_lebih_dari_5_mb_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        app(MemberPhotoService::class)->storeFromDataUrl('data:image/jpeg;base64,'.base64_encode(str_repeat('a', 5 * 1024 * 1024 + 1)));
    }

    public function test_isi_bukan_gambar_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        app(MemberPhotoService::class)->storeFromDataUrl('data:image/jpeg;base64,'.base64_encode('bukan gambar'));
    }
}
