<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\FollowUpChannel;
use App\Enums\FollowUpOutcome;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PtSessionStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\MemberFollowUp;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\PtPackage;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\CheckInService;
use App\Services\MemberCardService;
use App\Services\MembershipService;
use App\Services\PtService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba seluruh alur aplikasi secara manual.
 * Jalankan: php artisan db:seed --class=DemoSeeder
 *
 * Semua transaksi dibuat lewat service asli (bukan insert langsung) dengan tanggal mundur,
 * jadi angka di dashboard, laporan, dan komisi konsisten dengan aturan aplikasi.
 * Semua akun memakai password "password".
 */
class DemoSeeder extends Seeder
{
    private Carbon $sekarang;

    private User $owner;

    private User $pegawai;

    /** @var array<string, User> */
    private array $trainers = [];

    public function run(): void
    {
        if (Member::withTrashed()->exists()) {
            $this->command?->warn('Sudah ada data member — DemoSeeder dilewati. Jalankan "php artisan migrate:fresh --seed" dulu bila ingin mengulang.');

            return;
        }

        $this->sekarang = Carbon::now();

        $this->call([RoleSeeder::class, ExpenseCategorySeeder::class, MembershipPlanSeeder::class, ProductSeeder::class]);

        try {
            $this->staf();
            $this->paketPt();
            $this->promo();
            $this->member();
            $this->kasir();
            $this->pengeluaran();
            $this->pengumuman();
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Data demo selesai. Login: owner@repkard.test / pegawai@repkard.test / trainer1@repkard.test / budi@repkard.test — password: password');
    }

    /** Pindah "sekarang" ke N hari lalu pada jam tertentu. */
    private function mundur(int $hari, int $jam = 10, int $menit = 0): void
    {
        Carbon::setTestNow($this->sekarang->copy()->subDays($hari)->setTime($jam, $menit));
    }

    private function akun(string $nama, string $email, UserRole $role, ?string $phone = null): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nama, 'phone' => $phone, 'password' => 'password', 'is_active' => true, 'must_change_password' => false],
        );
        $user->email_verified_at ??= now();
        $user->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function staf(): void
    {
        $this->owner = $this->akun('Owner Repkard', 'owner@repkard.test', UserRole::Owner);
        $this->pegawai = $this->akun('Pegawai Repkard', 'pegawai@repkard.test', UserRole::Pegawai);

        foreach ([
            ['trainer1', 'Dimas Prakoso', 'trainer1@repkard.test', 'Strength & hipertrofi', 50000],
            ['trainer2', 'Sari Wulandari', 'trainer2@repkard.test', 'Yoga & penurunan berat badan', 60000],
        ] as [$key, $nama, $email, $spesialis, $komisi]) {
            $user = $this->akun($nama, $email, UserRole::Trainer);
            TrainerProfile::updateOrCreate(['user_id' => $user->id], [
                'specialties' => $spesialis,
                'bio' => "Trainer bersertifikat, spesialisasi {$spesialis}.",
                'commission_per_session' => $komisi,
            ]);
            $this->trainers[$key] = $user;
        }
    }

    private function paketPt(): void
    {
        foreach ([
            ['PT 4 Sesi', 4, 600000, 30],
            ['PT 8 Sesi', 8, 1100000, 60],
            ['PT 12 Sesi', 12, 1500000, 90],
        ] as $i => [$nama, $sesi, $harga, $hari]) {
            PtPackage::firstOrCreate(['name' => $nama], [
                'description' => "{$sesi} sesi personal training, berlaku {$hari} hari.",
                'sessions_count' => $sesi,
                'price' => $harga,
                'validity_days' => $hari,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function promo(): void
    {
        PromoCode::firstOrCreate(['code' => 'MEMBERBARU'], [
            'name' => 'Diskon member baru 10%',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'max_uses_per_member' => 1,
            'new_members_only' => true,
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);
        PromoCode::firstOrCreate(['code' => 'HEMAT50K'], [
            'name' => 'Potongan Rp 50.000',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 50000,
            'ends_on' => now()->addDays(30)->toDateString(),
            'max_uses' => 20,
            'max_uses_per_member' => 1,
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    private function buatMember(string $nama, string $email, string $gender, string $planNama, string $phone): Member
    {
        $user = $this->akun($nama, $email, UserRole::Member, $phone);

        return Member::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'gender' => $gender,
            'birth_date' => now()->subYears(rand(20, 40))->subDays(rand(0, 300))->toDateString(),
            'address' => 'Jl. Contoh No. '.rand(1, 99).', Kota Contoh',
            'emergency_contact_name' => 'Keluarga '.explode(' ', $nama)[0],
            'emergency_contact_phone' => '0812'.rand(10000000, 99999999),
            'selected_plan_id' => MembershipPlan::where('name', $planNama)->value('id'),
        ]);
    }

    private function aktifkan(Member $member, string $planNama, PaymentMethod $metode = PaymentMethod::Cash, ?PromoCode $promo = null, ?int $bayar = null): void
    {
        $plan = MembershipPlan::where('name', $planNama)->firstOrFail();
        $bayar ??= $plan->price;

        app(MembershipService::class)->activate($member, $plan, $metode, $bayar, $this->pegawai, null, $promo);
        app(MemberCardService::class)->issue($member->fresh(), $this->pegawai);
    }

    /** @param  list<int>  $hariLalu  check-in diurutkan dari yang paling lampau */
    private function checkIn(Member $member, array $hariLalu, int $jam = 18): void
    {
        $svc = app(CheckInService::class);

        foreach ($hariLalu as $hari) {
            $this->mundur($hari, $jam, rand(0, 50));
            $svc->confirm($svc->inspectMember($member->fresh()), $this->pegawai, CheckInService::METHOD_CARD);
        }
    }

    private function member(): void
    {
        $svc = app(MembershipService::class);
        $plans = fn (string $n) => MembershipPlan::where('name', $n)->firstOrFail();
        $m = [];

        // 1. Aktif, Bulanan, baru 10 hari, rajin datang (pakai promo).
        $this->mundur(10);
        $m['budi'] = $this->buatMember('Budi Santoso', 'budi@repkard.test', 'L', 'Bulanan', '081200000001');
        $this->aktifkan($m['budi'], 'Bulanan', PaymentMethod::Qris, PromoCode::where('code', 'MEMBERBARU')->first(), 180000);
        $this->checkIn($m['budi'], [10, 9, 7, 5, 4, 2, 1, 0]);

        // 2. Akan habis dalam 3 hari (muncul di widget "Akan habis").
        $this->mundur(27);
        $m['citra'] = $this->buatMember('Citra Lestari', 'citra@repkard.test', 'P', 'Bulanan', '081200000002');
        $this->aktifkan($m['citra'], 'Bulanan', PaymentMethod::Transfer);
        $this->checkIn($m['citra'], [26, 24, 21, 18, 15, 3]);

        // 3. Sudah kedaluwarsa (belum diperpanjang) + check-in yang ditolak.
        $this->mundur(50);
        $m['dewi'] = $this->buatMember('Dewi Anggraini', 'dewi@repkard.test', 'P', 'Bulanan', '081200000003');
        $this->aktifkan($m['dewi'], 'Bulanan');
        $this->checkIn($m['dewi'], [48, 45, 40, 33]);
        $this->mundur(5);
        $cek = app(CheckInService::class);
        $cek->confirm($cek->inspectMember($m['dewi']->fresh()), $this->pegawai, CheckInService::METHOD_CARD);

        // 4. Mulai menghilang: aktif, terakhir datang 14 hari lalu (halaman "Member Menghilang" + tindak lanjut).
        $this->mundur(25);
        $m['eko'] = $this->buatMember('Eko Prasetyo', 'eko@repkard.test', 'L', 'Bulanan', '081200000004');
        $this->aktifkan($m['eko'], 'Bulanan', PaymentMethod::Cash);
        $this->checkIn($m['eko'], [24, 20, 14]);
        $this->mundur(3, 11);
        MemberFollowUp::create([
            'member_id' => $m['eko']->id, 'user_id' => $this->pegawai->id, 'contacted_at' => now(),
            'channel' => FollowUpChannel::Chat, 'outcome' => FollowUpOutcome::Sibuk,
            'note' => 'Sedang lembur, janji datang minggu depan.', 'next_contact_on' => now()->addDays(5)->toDateString(),
        ]);

        // 5. Tahunan, aktif lama.
        $this->mundur(100);
        $m['fajar'] = $this->buatMember('Fajar Nugroho', 'fajar@repkard.test', 'L', 'Tahunan', '081200000005');
        $this->aktifkan($m['fajar'], 'Tahunan', PaymentMethod::Transfer);
        $this->checkIn($m['fajar'], [99, 95, 90, 70, 50, 30, 12, 6, 1]);

        // 6. Paket 10x: sebagian terpakai.
        $this->mundur(20);
        $m['gita'] = $this->buatMember('Gita Permata', 'gita@repkard.test', 'P', '10x Kunjungan', '081200000006');
        $this->aktifkan($m['gita'], '10x Kunjungan');
        $this->checkIn($m['gita'], [19, 17, 14, 12, 9, 7, 4]);

        // 7. Paket 10x: kuota habis (check-in berikutnya ditolak).
        $this->mundur(15);
        $m['hendra'] = $this->buatMember('Hendra Wijaya', 'hendra@repkard.test', 'L', '10x Kunjungan', '081200000007');
        $this->aktifkan($m['hendra'], '10x Kunjungan', PaymentMethod::Qris);
        $this->checkIn($m['hendra'], [14, 13, 12, 11, 10, 9, 8, 7, 6, 5, 2]);

        // 8. Dibekukan.
        $this->mundur(12);
        $m['indah'] = $this->buatMember('Indah Puspita', 'indah@repkard.test', 'P', 'Bulanan', '081200000008');
        $this->aktifkan($m['indah'], 'Bulanan');
        $this->checkIn($m['indah'], [11, 9]);
        $this->mundur(2);
        $svc->freeze($m['indah']->fresh()->currentMembership(), 10, $this->pegawai);
        $cek->confirm($cek->inspectMember($m['indah']->fresh()), $this->pegawai, CheckInService::METHOD_CARD); // ditolak: beku

        // 9. Diperpanjang (membership kedua menyusul yang pertama).
        $this->mundur(40);
        $m['joko'] = $this->buatMember('Joko Susilo', 'joko@repkard.test', 'L', 'Bulanan', '081200000009');
        $this->aktifkan($m['joko'], 'Bulanan');
        $this->checkIn($m['joko'], [38, 35, 30, 25, 20]);
        $this->mundur(12);
        $svc->renew($m['joko']->fresh(), $plans('Bulanan'), PaymentMethod::Cash, 200000, $this->pegawai);
        $this->checkIn($m['joko'], [10, 6, 3]);

        // 10. Upgrade paket (Bulanan -> 3 Bulan, ada kredit prorata).
        $this->mundur(10);
        $m['kartika'] = $this->buatMember('Kartika Dewi', 'kartika@repkard.test', 'P', 'Bulanan', '081200000010');
        $this->aktifkan($m['kartika'], 'Bulanan');
        $this->checkIn($m['kartika'], [9, 8, 6]);
        $this->mundur(4);
        $baru = $plans('3 Bulan');
        $lama = $m['kartika']->fresh()->currentMembership();
        $kredit = min($svc->remainingValue($lama), $baru->price);
        $svc->upgrade($m['kartika']->fresh(), $baru, PaymentMethod::Transfer, $baru->price - $kredit, $this->pegawai);
        $this->checkIn($m['kartika'], [3, 1]);

        // 11. Refund.
        $this->mundur(8);
        $m['lukman'] = $this->buatMember('Lukman Hakim', 'lukman@repkard.test', 'L', 'Bulanan', '081200000011');
        $this->aktifkan($m['lukman'], 'Bulanan');
        $this->checkIn($m['lukman'], [7]);
        $this->mundur(6);
        $svc->refund($m['lukman']->fresh()->currentMembership(), 100000, PaymentMethod::Cash, 'Pindah kota', $this->pegawai);

        // 12. Nonaktif.
        $this->mundur(70);
        $m['maya'] = $this->buatMember('Maya Sari', 'maya@repkard.test', 'P', 'Bulanan', '081200000012');
        $this->aktifkan($m['maya'], 'Bulanan');
        $this->mundur(30);
        $m['maya']->update(['status' => MemberStatus::Inactive]);

        // 13-14. Menunggu aktivasi (belum bayar) — untuk mencoba alur aktivasi sendiri.
        $this->mundur(1, 15);
        $this->buatMember('Nanda Pratama', 'nanda@repkard.test', 'L', 'Bulanan', '081200000013');
        $this->mundur(0, 8);
        $this->buatMember('Olivia Rahma', 'olivia@repkard.test', 'P', '3 Bulan', '081200000014');

        $this->pt($m);
    }

    /** @param  array<string, Member>  $m */
    private function pt(array $m): void
    {
        $pt = app(PtService::class);
        $paket = fn (string $n) => PtPackage::where('name', $n)->firstOrFail();

        // Budi + Dimas: 2 sesi selesai, 1 tidak datang, 1 dibatalkan, 1 terjadwal ke depan.
        $this->mundur(9, 9);
        $sub = $pt->sell($m['budi']->fresh(), $paket('PT 8 Sesi'), $this->trainers['trainer1'], PaymentMethod::Transfer, 1100000, $this->pegawai);

        foreach ([[8, PtSessionStatus::Completed], [6, PtSessionStatus::Completed], [4, PtSessionStatus::NoShow]] as [$hari, $status]) {
            $this->mundur($hari + 1, 8);
            $sesi = $pt->schedule($sub->fresh(), now()->addDay()->setTime(17, 0), 60, $this->pegawai);
            $this->mundur($hari, 18);
            $pt->finish($sesi, $status, $this->pegawai, $status === PtSessionStatus::NoShow ? 'Tidak ada kabar.' : 'Latihan dada & punggung.');
        }

        $this->mundur(3, 9);
        $batal = $pt->schedule($sub->fresh(), now()->addDays(2)->setTime(16, 0), 60, $this->pegawai);
        $pt->cancelSession($batal, $this->pegawai, 'Member sakit');

        Carbon::setTestNow();
        $pt->schedule($sub->fresh(), now()->addDays(2)->setTime(17, 0), 60, $this->pegawai);
        $pt->schedule($sub->fresh(), now()->addDays(4)->setTime(17, 0), 60, $this->pegawai);

        // Fajar + Sari: paket besar, beberapa sesi selesai, ada yang terjadwal besok.
        $this->mundur(20, 9);
        $sub2 = $pt->sell($m['fajar']->fresh(), $paket('PT 12 Sesi'), $this->trainers['trainer2'], PaymentMethod::Qris, 1500000, $this->pegawai);

        foreach ([18, 15, 11, 7] as $hari) {
            $this->mundur($hari + 1, 8);
            $sesi = $pt->schedule($sub2->fresh(), now()->addDay()->setTime(7, 30), 90, $this->pegawai);
            $this->mundur($hari, 9);
            $pt->finish($sesi, PtSessionStatus::Completed, $this->pegawai, 'Yoga & mobilitas.');
        }

        Carbon::setTestNow();
        $pt->schedule($sub2->fresh(), now()->addDay()->setTime(7, 30), 90, $this->pegawai);

        // Citra + Dimas: paket kecil baru dibeli, belum ada jadwal.
        $this->mundur(2, 14);
        $pt->sell($m['citra']->fresh(), $paket('PT 4 Sesi'), $this->trainers['trainer1'], PaymentMethod::Cash, 600000, $this->pegawai);

        Carbon::setTestNow();
        unset($m);
    }

    private function kasir(): void
    {
        $sale = app(SaleService::class);
        $produk = Product::pluck('id', 'name');
        $air = $produk['Air mineral 600ml'];
        $iso = $produk['Minuman isotonik'];
        $shake = $produk['Protein shake'];
        $handuk = $produk['Sewa handuk'];

        // Barang masuk.
        $this->mundur(14, 9);
        $sale->restock(Product::find($air), 40, 'Belanja grosir', $this->pegawai);
        $sale->restock(Product::find($shake), 15, 'Restock dari supplier', $this->pegawai);

        // Penjualan harian selama 2 minggu.
        $this->mundur(14);
        $voidTarget = null;

        for ($d = 13; $d >= 0; $d--) {
            $this->mundur(14 - $d, 19);
            $s = $sale->checkout([$air => rand(1, 3), $iso => rand(0, 2) ?: null], PaymentMethod::Cash, 100000, null, 'Tamu', $this->pegawai);
            $voidTarget ??= $s;

            if ($d % 2 === 0) {
                $this->mundur(14 - $d, 20);
                $sale->checkout([$shake => 1, $handuk => 1], PaymentMethod::Qris, null, null, 'Tamu', $this->pegawai);
            }
        }

        $this->mundur(10, 21);
        $sale->void($voidTarget, $this->owner, 'Salah input jumlah');

        // Walk-in harian.
        foreach ([[12, 'Rizky'], [9, 'Tika'], [9, 'Andi'], [5, 'Wulan'], [2, 'Bagas'], [0, 'Putri']] as [$hari, $nama]) {
            $this->mundur($hari, 16);
            WalkIn::create([
                'name' => $nama, 'amount' => 25000, 'method' => $hari % 2 ? PaymentMethod::Cash : PaymentMethod::Qris,
                'recorded_by' => $this->pegawai->id, 'visited_at' => now(),
            ]);
        }

        Carbon::setTestNow();
    }

    private function pengeluaran(): void
    {
        $kategori = ExpenseCategory::pluck('id', 'name');

        // Bulan ini dan bulan lalu supaya laporan bulanan punya pembanding.
        foreach ([
            [0, 'Listrik & air', 850000, 'Tagihan listrik & PDAM'],
            [0, 'Kebersihan', 150000, 'Cairan pel & sabun'],
            [0, 'Perawatan & servis alat', 400000, 'Servis treadmill'],
            [0, 'Promosi', 300000, 'Cetak brosur'],
            [1, 'Sewa tempat', 5000000, 'Sewa ruko bulan lalu'],
            [1, 'Gaji & honor', 3500000, 'Honor penjaga'],
            [1, 'Listrik & air', 780000, 'Tagihan listrik & PDAM'],
        ] as [$bulanLalu, $kat, $jumlah, $ket]) {
            Expense::create([
                'expense_category_id' => $kategori[$kat],
                'amount' => $jumlah,
                'spent_on' => now()->subMonthsNoOverflow($bulanLalu)->startOfMonth()->addDays(rand(1, 9))->min(now())->toDateString(),
                'description' => $ket,
                'recorded_by' => $this->owner->id,
            ]);
        }
    }

    private function pengumuman(): void
    {
        foreach ([
            ['Jam operasional Lebaran', 'Gym tutup 2 hari saat libur nasional. Buka kembali sesuai jadwal biasa.', true, true],
            ['Promo bulan ini', 'Gunakan kode HEMAT50K saat perpanjang membership.', false, true],
            ['Perawatan alat', 'Area cardio ditutup sebentar Sabtu pagi untuk servis.', false, false],
        ] as [$judul, $isi, $pin, $landing]) {
            Announcement::create([
                'title' => $judul, 'body' => $isi, 'starts_at' => now()->subDays(2), 'ends_at' => null,
                'is_pinned' => $pin, 'show_on_landing' => $landing, 'created_by' => $this->owner->id,
            ]);
        }
    }
}
