<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MemberPhotoController extends Controller
{
    public function __invoke(Member $member): Response
    {
        Gate::authorize('viewPhoto', $member);

        abort_unless(
            $member->photo_path && Storage::disk('local')->exists($member->photo_path),
            404,
        );

        return Storage::disk('local')->response($member->photo_path, null, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
