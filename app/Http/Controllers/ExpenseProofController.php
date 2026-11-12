<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ExpenseProofController extends Controller
{
    public function __invoke(Expense $expense): Response
    {
        Gate::authorize('view', $expense);

        abort_unless(
            $expense->proof_path && Storage::disk('local')->exists($expense->proof_path),
            404,
        );

        return Storage::disk('local')->response($expense->proof_path, null, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
