<?php

namespace Tests\Feature;

use App\Models\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumniDashboardTest extends TestCase
{
    /**
     * Satu baris alumni yang sudah mengirim respon, supaya ringkasan jawaban
     * dan riwayat partisipasi ikut terbentuk.
     */
    private function alumniSudahMengisi(): object
    {
        return DB::table('alumni')
            ->join('respon', 'respon.alumni_npm', '=', 'alumni.npm_mhs')
            ->where('respon.status', 'submitted')
            ->whereNotNull('alumni.kode_prg')
            ->where('alumni.kode_prg', '<>', '')
            ->first(['alumni.npm_mhs', 'alumni.nama_mhs', 'alumni.kode_prg']);
    }

    public function test_login_alumni_berhasil_dan_akun_dibuat(): void
    {
        $alumni = $this->alumniSudahMengisi();

        DB::beginTransaction();
        try {
            $this->post('/login', [
                'npm' => $alumni->npm_mhs,
                'kode_prg' => $alumni->kode_prg,
            ])->assertRedirect('/alumni/dashboard');

            $this->assertAuthenticated();

            $user = User::where('mahasiswa_npm', $alumni->npm_mhs)->firstOrFail();

            $this->assertTrue($user->isAlumni());
            $this->assertSame($alumni->npm_mhs, $user->username);
            $this->assertNotNull($user->last_login_at);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Ringkasan jawaban hanya tampil untuk periode yang sedang aktif, dan
     * periode mana yang aktif adalah keputusan admin di produksi. Tes
     * menetapkannya sendiri supaya hasilnya tidak ikut berubah.
     *
     * Harus dipanggil di dalam transaksi uji.
     */
    private function aktifkanPeriodeBerisi(): int
    {
        $periodeId = DB::table('respon')
            ->where('status', 'submitted')
            ->groupBy('periode_tracer_id')
            ->orderByRaw('count(*) desc')
            ->value('periode_tracer_id');

        $this->assertNotNull($periodeId, 'tidak ada periode dengan respon terkirim');

        DB::table('periode_tracer')->update(['is_active' => false]);
        DB::table('periode_tracer')->where('id', $periodeId)->update(['is_active' => true]);

        return (int) $periodeId;
    }

    public function test_dashboard_alumni_render(): void
    {
        $alumni = $this->alumniSudahMengisi();

        DB::beginTransaction();
        try {
            $this->aktifkanPeriodeBerisi();

            $user = $this->akun($alumni->npm_mhs, trim($alumni->nama_mhs));

            $this->actingAs($user)
                ->get('/alumni/dashboard')
                ->assertOk()
                ->assertSee('Kuesioner Tracer Study')
                ->assertSee('Data Kelulusan')
                ->assertSee('Riwayat Partisipasi')
                ->assertSee('Ringkasan Jawaban Anda')
                ->assertSee($alumni->npm_mhs);
        } finally {
            DB::rollBack();
        }
    }

    public function test_dashboard_alumni_tanpa_data_kelulusan_tetap_render(): void
    {
        DB::beginTransaction();
        try {
            // NPM yang tidak ada di tabel alumni: halaman harus memberi
            // peringatan, bukan error.
            $user = $this->akun('NPM-UJI-TANPA-DATA', 'Alumni Uji');

            $this->actingAs($user)
                ->get('/alumni/dashboard')
                ->assertOk()
                ->assertSee('Data kelulusan Anda tidak ditemukan');
        } finally {
            DB::rollBack();
        }
    }

    public function test_alumni_tidak_boleh_masuk_area_admin_dan_prodi(): void
    {
        $alumni = $this->alumniSudahMengisi();

        DB::beginTransaction();
        try {
            $user = $this->akun($alumni->npm_mhs, trim($alumni->nama_mhs));

            $this->actingAs($user)->get('/admin/dashboard')->assertStatus(401);
            $this->actingAs($user)->get('/prodi/dashboard')->assertStatus(401);
        } finally {
            DB::rollBack();
        }
    }

    public function test_admin_tidak_boleh_masuk_area_alumni(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/alumni/dashboard')
            ->assertStatus(401);
    }

    public function test_logout_alumni_kembali_ke_login_publik(): void
    {
        $alumni = $this->alumniSudahMengisi();

        DB::beginTransaction();
        try {
            $user = $this->akun($alumni->npm_mhs, trim($alumni->nama_mhs));

            $this->actingAs($user)
                ->post('/logout')
                ->assertRedirect('/login');

            $this->assertGuest();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Buat akun alumni berperan 'alumni' di dalam transaksi uji.
     */
    private function akun(string $npm, string $nama): User
    {
        $user = User::create([
            'name' => $nama ?: ('Alumni ' . $npm),
            'username' => $npm,
            'email' => $npm . '@alumni.ummetro.ac.id',
            'mahasiswa_npm' => $npm,
            'password' => bcrypt('rahasia-uji'),
            'is_active' => true,
        ]);

        $user->roles()->attach(Roles::where('name', Roles::ALUMNI)->firstOrFail()->id);

        return $user;
    }
}
