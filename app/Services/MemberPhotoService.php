<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class MemberPhotoService
{
    public const MAX_KB = 5120;

    /**
     * Proses foto (rasio 3:4, JPEG) dan simpan di disk private. Mengembalikan path.
     */
    public function store(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages(['photo' => 'Ukuran foto maksimal 5 MB.']);
        }

        try {
            $encoded = Image::decodePath($file->getRealPath())->cover(600, 800)->encode(new JpegEncoder(quality: 85));
        } catch (Throwable) {
            throw ValidationException::withMessages(['photo' => 'File harus berupa gambar.']);
        }

        $path = 'member-photos/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, (string) $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
