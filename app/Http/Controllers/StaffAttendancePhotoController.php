<?php

namespace App\Http\Controllers;

use App\Models\StaffAttendance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StaffAttendancePhotoController extends Controller
{
    public function __invoke(StaffAttendance $attendance, string $jenis): Response
    {
        abort_unless(in_array($jenis, ['masuk', 'pulang'], true), 404);

        Gate::authorize('view', $attendance);

        $path = $jenis === 'masuk' ? $attendance->clock_in_photo_path : $attendance->clock_out_photo_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
