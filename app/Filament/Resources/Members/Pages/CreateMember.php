<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Models\Member;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected ?string $passwordSementara = null;

    protected function handleRecordCreation(array $data): Model
    {
        $this->passwordSementara = Str::password(10, symbols: false);

        return DB::transaction(function () use ($data): Member {
            $user = new User([
                'name' => $data['name'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : MemberForm::emailDariHp($data['phone']),
                'phone' => $data['phone'],
                'password' => $this->passwordSementara,
                'must_change_password' => true,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(UserRole::Member->value);

            return $user->member()->create([
                'phone' => $data['phone'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'photo_path' => $data['photo_upload'] ?? null,
                'selected_plan_id' => $data['selected_plan_id'] ?? null,
                'status' => MemberStatus::Pending,
            ]);
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Member terdaftar')
            ->body("Password sementara: {$this->passwordSementara} — catat sekarang, tidak akan ditampilkan lagi.")
            ->persistent();
    }
}
