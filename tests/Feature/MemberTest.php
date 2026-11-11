<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
    }

    private function userDenganRole(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function dataMember(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => null,
            'phone' => '081234567890',
            'gender' => 'L',
            'birth_date' => '1995-05-17',
            'address' => 'Jl. Mawar 1',
            'emergency_contact_name' => 'Siti',
            'emergency_contact_phone' => '081111111111',
        ], $override);
    }

    private function memberDenganFoto(): Member
    {
        $user = User::factory()->create()->assignRole(UserRole::Member->value);
        $path = 'member-photos/contoh.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('a.jpg')->getContent());

        return Member::factory()->create(['user_id' => $user->id, 'photo_path' => $path]);
    }

    public function test_pegawai_mendaftarkan_member_dengan_foto_private(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $paket = MembershipPlan::factory()->create();

        Livewire::test(CreateMember::class)
            ->fillForm($this->dataMember(['selected_plan_id' => $paket->id]) + [
                'photo_upload' => UploadedFile::fake()->image('foto.png', 1000, 1000),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::firstOrFail();
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertSame($paket->id, $member->selected_plan_id);
        $this->assertTrue($member->user->hasRole(UserRole::Member->value));
        $this->assertTrue($member->user->must_change_password);
        $this->assertNotNull($member->user->email_verified_at);
        $this->assertSame('081234567890@member.repkard.local', $member->user->email);
        $this->assertStringStartsWith('member-photos/', $member->photo_path);
        Storage::disk('local')->assertExists($member->photo_path);

        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($member->photo_path));
        $this->assertSame([600, 800], [$w, $h]);
    }

    public function test_foto_tidak_bisa_diakses_lewat_storage_publik(): void
    {
        $member = $this->memberDenganFoto();

        foreach (['/storage/', '/storage/private/'] as $awalan) {
            $this->assertNotSame(200, $this->get($awalan.$member->photo_path)->getStatusCode());
        }
        $this->assertFileDoesNotExist(public_path('storage/'.$member->photo_path));
    }

    public function test_route_foto_dicek_izinnya(): void
    {
        $a = $this->memberDenganFoto();
        $b = $this->memberDenganFoto();

        $this->get(route('member.photo', $a))->assertRedirect();

        $this->actingAs($a->user)->get(route('member.photo', $b))->assertForbidden();

        $respons = $this->actingAs($a->user)->get(route('member.photo', $a));
        $respons->assertOk();
        $this->assertStringContainsString('private', $respons->headers->get('Cache-Control'));

        $this->actingAs($this->userDenganRole(UserRole::Pegawai))
            ->get(route('member.photo', $a))->assertOk();
    }

    public function test_route_foto_404_bila_tidak_ada_foto(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->userDenganRole(UserRole::Owner))
            ->get(route('member.photo', $member))->assertNotFound();
    }

    public function test_hanya_owner_bisa_menghapus_member(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $this->assertFalse(auth()->user()->can('delete', $member));
        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertActionHidden('delete');
        $this->assertNotSoftDeleted($member);

        $this->actingAs($this->userDenganRole(UserRole::Owner));
        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->callAction('delete');
        $this->assertSoftDeleted($member);
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));

        Livewire::test(CreateMember::class)
            ->fillForm($this->dataMember() + [
                'photo_upload' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['photo_upload']);

        $this->assertSame(0, Member::count());
    }

    public function test_pencarian_berdasarkan_nomor_hp(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $cari = Member::factory()->create(['phone' => '085551230000']);
        $lain = Member::factory()->create(['phone' => '081999999999']);

        Livewire::test(ListMembers::class)
            ->searchTable('085551230000')
            ->assertCanSeeTableRecords([$cari])
            ->assertCanNotSeeTableRecords([$lain]);
    }

    public function test_pegawai_menonaktifkan_member_dan_mengganti_foto_menghapus_file_lama(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $member = $this->memberDenganFoto();
        $lama = $member->photo_path;

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->fillForm([
                'status' => MemberStatus::Inactive->value,
                'photo_upload' => UploadedFile::fake()->image('baru.jpg', 800, 800),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame(MemberStatus::Inactive, $member->status);
        $this->assertNotSame($lama, $member->photo_path);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($member->photo_path);
    }

    public function test_hapus_permanen_menghapus_file_foto(): void
    {
        $member = $this->memberDenganFoto();

        $member->forceDelete();

        Storage::disk('local')->assertMissing('member-photos/contoh.jpg');
    }
}
