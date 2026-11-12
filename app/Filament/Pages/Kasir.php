<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Locked;

class Kasir extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Kasir';

    protected static ?string $navigationLabel = 'Kasir';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected string $view = 'filament.pages.kasir';

    private const MAX_CHECKOUT_PER_MENIT = 30;

    /** Hanya product_id => jumlah. Harga dan total selalu dibaca ulang dari database. */
    #[Locked]
    public array $cart = [];

    #[Locked]
    public ?int $lastSaleId = null;

    public string $search = '';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['method' => PaymentMethod::Cash->value]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('member_id')
                ->label('Member (opsional)')
                ->searchable()
                ->live()
                ->getSearchResultsUsing(fn (string $search): array => Member::query()
                    ->with('user')
                    ->where(fn ($q) => $q
                        ->where('member_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")))
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(fn (Member $m): array => [$m->getKey() => "{$m->user?->name} ({$m->member_number})"])
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => Member::with('user')->find($value)?->user?->name),
            TextInput::make('customer_name')
                ->label('Nama pembeli (opsional)')
                ->maxLength(255)
                ->visible(fn (callable $get): bool => blank($get('member_id'))),
            Select::make('method')
                ->label('Metode bayar')
                ->options(PaymentMethod::class)
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('cash_received')
                ->label('Uang diterima (Rp)')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->inputMode('numeric')
                ->live(debounce: 300)
                ->visible(fn (callable $get): bool => self::isCash($get('method'))),
        ]);
    }

    /** Nilai Select metode bisa berupa string (dari browser) atau enum (dari state form). */
    private static function isCash(mixed $method): bool
    {
        return $method === PaymentMethod::Cash || $method === PaymentMethod::Cash->value;
    }

    /**
     * Produk aktif untuk grid (dicari lewat kotak pencarian).
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        return Product::query()
            ->active()
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('sku', 'like', '%'.trim($this->search).'%')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Isi keranjang dengan harga dari database.
     *
     * @return Collection<int, array{product: Product, quantity: int, subtotal: int}>
     */
    public function lines(): Collection
    {
        if ($this->cart === []) {
            return collect();
        }

        $products = Product::query()->whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)
            ->filter(fn ($qty, $id): bool => $products->has($id))
            ->map(fn (int $qty, int $id): array => [
                'product' => $products[$id],
                'quantity' => $qty,
                'subtotal' => $products[$id]->price * $qty,
            ])
            ->values();
    }

    public function total(): int
    {
        return (int) $this->lines()->sum('subtotal');
    }

    public function change(): ?int
    {
        $cash = $this->data['cash_received'] ?? null;

        if (($this->data['method'] ?? null) !== PaymentMethod::Cash->value || $cash === null || $cash === '') {
            return null;
        }

        return (int) $cash - $this->total();
    }

    public function lastSale(): ?Sale
    {
        return $this->lastSaleId ? Sale::query()->find($this->lastSaleId) : null;
    }

    public function add(int $productId): void
    {
        $product = Product::query()->active()->find($productId);

        if (! $product) {
            return;
        }

        $qty = ($this->cart[$productId] ?? 0) + 1;

        if ($product->track_stock && $qty > $product->stock) {
            Notification::make()->warning()->title("Stok {$product->name} tinggal {$product->stock}")->send();

            return;
        }

        $this->cart[$productId] = $qty;
    }

    public function decrease(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId] <= 1) {
            unset($this->cart[$productId]);

            return;
        }

        $this->cart[$productId]--;
    }

    public function remove(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function bayar(): void
    {
        $key = 'kasir-checkout:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::MAX_CHECKOUT_PER_MENIT)) {
            Notification::make()->danger()->title('Terlalu banyak transaksi, coba lagi sebentar lagi')->send();

            return;
        }

        RateLimiter::hit($key, 60);

        $state = $this->form->getState();
        $raw = $state['method'] ?? null;
        $method = $raw instanceof PaymentMethod ? $raw : PaymentMethod::tryFrom((string) $raw);

        if (! $method) {
            Notification::make()->danger()->title('Pilih metode bayar')->send();

            return;
        }

        $cash = $method === PaymentMethod::Cash && filled($state['cash_received'] ?? null)
            ? (int) $state['cash_received']
            : null;
        $member = filled($state['member_id'] ?? null) ? Member::find($state['member_id']) : null;

        try {
            $sale = app(SaleService::class)->checkout(
                $this->cart,
                $method,
                $cash,
                $member,
                $state['customer_name'] ?? null,
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->cart = [];
        $this->lastSaleId = $sale->getKey();
        $this->form->fill(['method' => PaymentMethod::Cash->value]);

        $body = $sale->change() !== null ? 'Kembalian '.PaymentResource::rupiah($sale->change()) : null;

        Notification::make()
            ->success()
            ->title("Transaksi {$sale->number} berhasil")
            ->body($body)
            ->actions([
                Action::make('struk')
                    ->label('Cetak struk')
                    ->url(route('kasir.receipt', $sale), shouldOpenInNewTab: true),
            ])
            ->persistent()
            ->send();
    }
}
