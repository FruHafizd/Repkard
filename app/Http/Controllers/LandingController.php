<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing', ['plans' => MembershipPlan::active()->get()]);
    }
}
