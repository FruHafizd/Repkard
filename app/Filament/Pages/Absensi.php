<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\WebcamPhoto;
use App\Models\StaffAttendance;
use App\Services\AttendanceService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Absen masuk / pulang dengan swafoto webcam. Hanya untuk pegawai & trainer (Owner tidak absen).
 */
class Absensi extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Absensi';

    protected static ?string $navigationLabel = 'Absensi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected string $view = 'filament.pages.absensi';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()?->user();

        return $user !== null
            && AttendanceService::canAttend($user)
            && $user->can('View:Absensi');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            WebcamPhoto::make('photo')
                ->label('Swafoto')
                ->live(),
        ]);
    }

    public function absen(): void
    {
        $user = auth()->user();
        $photo = $this->data['photo'] ?? null;

        if (! AttendanceService::canAttend($user)) {
            abort(403);
        }

        if (blank($photo)) {
            Notification::make()->danger()->title('Ambil swafoto terlebih dahulu')->send();

            return;
        }

        $service = app(AttendanceService::class);
        $today = $service->today($user);

        try {
            if ($today === null) {
                $service->clockIn($user, $photo);
                $title = 'Absen masuk tercatat';
            } elseif ($today->clock_out_at === null) {
                $service->clockOut($user, $photo);
                $title = 'Absen pulang tercatat';
            } else {
                Notification::make()->warning()->title('Anda sudah absen pulang hari ini')->send();

                return;
            }
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }

        $this->form->fill();
        Notification::make()->success()->title($title)->send();
    }

    /** @return Collection<int, StaffAttendance> */
    public function riwayat(): Collection
    {
        return StaffAttendance::query()
            ->where('user_id', auth()->id())
            ->whereBetween('clock_in_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->latest('clock_in_at')
            ->get();
    }

    protected function getViewData(): array
    {
        return [
            'today' => app(AttendanceService::class)->today(auth()->user()),
            'riwayat' => $this->riwayat(),
        ];
    }
}
