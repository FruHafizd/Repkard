<?php

namespace App\Filament\Member\Pages\Auth;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\PromoService;
use Closure;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use SensitiveParameter;

class Register extends BaseRegister
{
    public function mount(): void
    {
        parent::mount();

        // Paket dari tautan landing page; abaikan bila tidak aktif / tidak ada.
        $planId = request()->query('plan');
        $plan = MembershipPlan::active()->find($planId);

        $this->form->fill(['selected_plan_id' => $plan?->id]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            TextInput::make('phone')
                ->label('Nomor HP')
                ->tel()
                ->required()
                ->maxLength(20),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getPlanFormComponent(),
            $this->getPromoFormComponent(),
        ]);
    }

    protected function getPlanFormComponent(): Component
    {
        return Select::make('selected_plan_id')
            ->label('Paket')
            ->options(fn (): array => MembershipPlan::active()->pluck('name', 'id')->all())
            ->required()
            // Validasi server: hanya paket aktif.
            ->rule(Rule::exists('membership_plans', 'id')->where(fn (Builder $q) => $q->where('is_active', true)));
    }

    protected function getPromoFormComponent(): Component
    {
        return TextInput::make('promo_code')
            ->label('Kode promo (opsional)')
            ->maxLength(30)
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $plan = MembershipPlan::active()->find($get('selected_plan_id'));

                if (blank($value) || ! $plan) {
                    return;
                }

                // Calon member belum punya riwayat pembayaran: pakai Member kosong (belum tersimpan).
                $hasil = app(PromoService::class)->check($value, $plan, new Member, isActivation: true);

                if (! $hasil->valid) {
                    $fail($hasil->message);
                }
            });
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
            $user->assignRole(UserRole::Member->value);

            $user->member()->create([
                'phone' => $data['phone'],
                'status' => MemberStatus::Pending,
                'selected_plan_id' => $data['selected_plan_id'],
                'requested_promo_code' => filled($data['promo_code'] ?? null) ? strtoupper(trim($data['promo_code'])) : null,
            ]);

            return $user;
        });
    }
}
