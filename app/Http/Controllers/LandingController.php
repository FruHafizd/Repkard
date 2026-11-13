<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\MembershipPlan;
use App\Models\PtPackage;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing', [
            'plans' => MembershipPlan::active()->get(),
            'ptPackages' => PtPackage::active()->get(),
            'announcements' => Announcement::query()->published()->where('show_on_landing', true)->limit(2)->get(),
        ]);
    }

    public function paketPt(): View
    {
        return view('paket-pt', ['ptPackages' => PtPackage::active()->get()]);
    }
}
