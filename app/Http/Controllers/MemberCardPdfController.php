<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\CardTokenService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MemberCardPdfController extends Controller
{
    public function __invoke(Member $member, CardTokenService $tokens): Response
    {
        Gate::authorize('downloadCard', $member);

        $card = $member->activeCard();
        abort_unless($card, 404);

        $member->load('user');

        $qr = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'outputBase64' => true,
        ])))->render($tokens->make($card));

        $photo = null;
        if ($member->photo_path && Storage::disk('local')->exists($member->photo_path)) {
            $photo = 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($member->photo_path));
        }

        return Pdf::loadView('cards.pdf', [
            'member' => $member,
            'card' => $card,
            'qr' => $qr,
            'photo' => $photo,
        ])
            ->setPaper([0, 0, 242.65, 153.07])
            ->stream('kartu-'.$member->member_number.'.pdf');
    }
}
