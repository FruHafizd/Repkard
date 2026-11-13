<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\MembershipPlan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing', [
            'plans' => MembershipPlan::active()->get(),
            'announcements' => Announcement::query()->published()->where('show_on_landing', true)->limit(2)->get(),
        ]);
    }

    public function pengumuman(): View
    {
        return view('pengumuman', [
            'announcements' => Announcement::query()->published()->where('show_on_landing', true)->paginate(10),
        ]);
    }
}
