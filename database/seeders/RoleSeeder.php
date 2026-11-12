<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::firstOrCreate(['name' => UserRole::Owner->value, 'guard_name' => 'web']);
        $pegawai = Role::firstOrCreate(['name' => UserRole::Pegawai->value, 'guard_name' => 'web']);
        $trainer = Role::firstOrCreate(['name' => UserRole::Trainer->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => UserRole::Member->value, 'guard_name' => 'web']);

        // Buat permission untuk semua Resource/Page admin (Shield).
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions', '--no-interaction' => true]);

        // Owner = super admin Shield: memegang semua permission.
        $owner->syncPermissions(Permission::all());

        // Izin default pegawai: hanya melihat daftar pegawai.
        $pegawai->givePermissionTo([
            'ViewAny:User', 'View:User',
            'ViewAny:MembershipPlan', 'View:MembershipPlan',
            'ViewAny:Member', 'View:Member', 'Create:Member', 'Update:Member',
            'ReissueCard:Member',
            // Hanya transaksi yang dicatat sendiri; "ViewAll:Payment" sengaja tidak diberikan.
            'ViewAny:Payment', 'View:Payment',
            // Pendatang: hanya catatan sendiri; "ViewAll:WalkIn" sengaja tidak diberikan.
            'ViewAny:WalkIn', 'View:WalkIn', 'Create:WalkIn',
            'View:ScanCheckIn', 'ViewAny:CheckIn', 'View:CheckIn',
            'View:MemberMenghilang',
            // Pengumuman: boleh buat, ubah miliknya sendiri; "ManageAll" dan "Delete" sengaja tidak diberikan.
            'ViewAny:Announcement', 'View:Announcement', 'Create:Announcement', 'Update:Announcement',
            // Promo: pegawai hanya melihat; membuat/mengubah hanya owner.
            'ViewAny:PromoCode', 'View:PromoCode',
            // Kasir: lihat produk, jual, dan lihat penjualan sendiri; "ViewAll:Sale", "Cancel:Sale", "ManageStock:Product" sengaja tidak diberikan.
            'ViewAny:Product', 'View:Product',
            'ViewAny:Sale', 'View:Sale',
            'View:Kasir',
            // Personal trainer: pegawai menjual paket PT dan melihat semua klien PT; paket PT hanya dilihat.
            'ViewAny:PtPackage', 'View:PtPackage',
            'ViewAny:PtSubscription', 'View:PtSubscription', 'ViewAll:PtSubscription',
            'SellPt:Member',
        ]);

        // Trainer: akses sempit, hanya paket PT dan klien PT miliknya sendiri.
        $trainer->syncPermissions([
            'ViewAny:PtPackage', 'View:PtPackage',
            'ViewAny:PtSubscription', 'View:PtSubscription',
        ]);
    }
}
