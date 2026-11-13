<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Member;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    /**
     * @param  array<int, int>  $items  product_id => jumlah. Harga selalu dibaca dari database.
     */
    public function checkout(
        array $items,
        PaymentMethod $method,
        ?int $cashReceived,
        ?Member $member,
        ?string $customerName,
        User $by,
    ): Sale {
        $items = array_filter($items, fn ($qty): bool => $qty !== null);

        if ($items === []) {
            throw new InvalidArgumentException('Keranjang masih kosong.');
        }

        foreach ($items as $qty) {
            if (! is_int($qty) || $qty < 1) {
                throw new InvalidArgumentException('Jumlah barang minimal 1.');
            }
        }

        ksort($items);

        $simpan = function () use ($items, $method, $cashReceived, $member, $customerName, $by): Sale {
            $products = Product::query()
                ->whereIn('id', array_keys($items))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $total = 0;

            foreach ($items as $productId => $qty) {
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || ! $product->is_active) {
                    throw new InvalidArgumentException('Produk tidak tersedia untuk dijual.');
                }

                if ($product->track_stock && $product->stock < $qty) {
                    throw new InvalidArgumentException("Stok {$product->name} tinggal {$product->stock}");
                }

                $subtotal = $product->price * $qty;
                $total += $subtotal;
                $lines[] = [$product, $qty, $subtotal];
            }

            if ($method === PaymentMethod::Cash) {
                if ($cashReceived === null || $cashReceived < $total) {
                    throw new InvalidArgumentException('Uang diterima kurang dari total.');
                }
            } else {
                $cashReceived = null;
            }

            $sale = Sale::create([
                'number' => $this->nextNumber(),
                'member_id' => $member?->getKey(),
                'customer_name' => $member ? null : ($customerName !== null && trim($customerName) !== '' ? trim($customerName) : null),
                'total' => $total,
                'method' => $method,
                'cash_received' => $cashReceived,
                'recorded_by' => $by->getKey(),
                'sold_at' => now(),
            ]);

            foreach ($lines as [$product, $qty, $subtotal]) {
                $sale->items()->create([
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                ]);

                if ($product->track_stock) {
                    $product->decrement('stock', $qty);
                    $this->movement($product, -$qty, StockMovementType::Sale, null, $by, $sale);
                }
            }

            return $sale;
        };

        // Dua kasir bisa membaca nomor terakhir yang sama; unique index menolak yang kalah, lalu transaksi diulang dengan nomor baru.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return DB::transaction($simpan);
            } catch (UniqueConstraintViolationException $e) {
                if ($percobaan >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function void(Sale $sale, User $by, string $reason): void
    {
        DB::transaction(function () use ($sale, $by, $reason): void {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($sale->isCancelled()) {
                throw new InvalidArgumentException('Transaksi ini sudah dibatalkan.');
            }

            if (trim($reason) === '') {
                throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
            }

            foreach ($sale->items()->orderBy('product_id')->get() as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product && $product->track_stock) {
                    $product->increment('stock', $item->quantity);
                    $this->movement($product, $item->quantity, StockMovementType::Void, "Batal {$sale->number}", $by, $sale);
                }
            }

            $sale->update([
                'cancelled_at' => now(),
                'cancelled_by' => $by->getKey(),
                'cancel_reason' => $reason,
            ]);
        });
    }

    public function restock(Product $product, int $qty, ?string $note, User $by): void
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Jumlah barang masuk minimal 1.');
        }

        DB::transaction(function () use ($product, $qty, $note, $by): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $product->increment('stock', $qty);
            $this->movement($product, $qty, StockMovementType::Restock, $note, $by);
        });
    }

    public function adjust(Product $product, int $newStock, string $reason, User $by): void
    {
        if ($newStock < 0) {
            throw new InvalidArgumentException('Stok tidak boleh negatif.');
        }

        DB::transaction(function () use ($product, $newStock, $reason, $by): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $diff = $newStock - $product->stock;

            $product->update(['stock' => $newStock]);

            if ($diff !== 0) {
                $this->movement($product, $diff, StockMovementType::Adjustment, $reason, $by);
            }
        });
    }

    /** Format TRX-YYMMDD-NNNN, urut per hari. Dipanggil di dalam transaction. */
    private function nextNumber(): string
    {
        $prefix = 'TRX-'.now()->format('ymd').'-';

        $last = Sale::query()
            ->where('number', 'like', $prefix.'%')
            ->max('number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function movement(Product $product, int $qty, StockMovementType $type, ?string $note, User $by, ?Sale $sale = null): void
    {
        $product->movements()->create([
            'quantity' => $qty,
            'type' => $type,
            'note' => $note,
            'sale_id' => $sale?->getKey(),
            'user_id' => $by->getKey(),
        ]);
    }
}
