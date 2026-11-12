<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SaleReceiptController extends Controller
{
    /** Lebar kertas struk 80 mm = 226.77pt. */
    private const WIDTH = 226.77;

    public function __invoke(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        $sale->load(['items', 'recorder', 'member.user']);

        // Tinggi menyesuaikan jumlah item (dompdf butuh tinggi tetap).
        $height = 260 + $sale->items->count() * 30 + ($sale->isCancelled() ? 40 : 0) + ($sale->cash_received !== null ? 30 : 0);

        return Pdf::loadView('receipts.sale', ['sale' => $sale])
            ->setPaper([0, 0, self::WIDTH, $height])
            ->stream('struk-'.$sale->number.'.pdf');
    }
}
