<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['member.user', 'membership.plan', 'recorder']);

        return Pdf::loadView('receipts.payment', ['payment' => $payment])
            ->setPaper('a6')
            ->stream('kuitansi-'.$payment->getKey().'.pdf');
    }
}
