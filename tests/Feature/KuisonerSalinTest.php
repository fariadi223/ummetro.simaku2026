<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\PeriodeTracer;
use App\Models\Pertanyaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KuisonerSalinTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function asal(): PeriodeTracer
    {
        return PeriodeTracer::where('nama', 'Tracer Study 2021')->firstOrFail();
    }

    private function salin(array $ganti = []): array
    {
        return $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/periode/' . $this->asal()->id . '/salin', array_merge([
                'nama' => 'Salinan Uji Otomatis',
                'tahun_lulusan' => 2027,
                'tanggal_mulai' => '2027-01-01',
                'tanggal_selesai' => '2027-12-31',
            ], $ganti))
            ->assertStatus(201)
            ->json();
    }

    public function test_tombol_dan_modal_salin_ada_di_halaman(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/kuisoner')
            ->assertOk()
            ->assertSee('on-periode-salin', false)
            ->assertSee('modal-salin', false)
            ->assertSee('form-salin', false);
    }

    public function test_struktur_tersalin_utuh(): void
    {
        DB::beginTransaction();
        try {
            $asal = $this->asal();
            $kAsal = $asal->kuesioner()->firstOrFail();

            $jmlBagian = $kAsal->bagian()->count();
            $jmlSoal = Pertanyaan::whereIn('kuesioner_bagian_id', $kAsal->bagian()->pluck('id'))->count();
            $jmlOpsi = DB::table('pertanyaan_opsi')
                ->whereIn('pertanyaan_id', Pertanyaan::whereIn('kuesioner_bagian_id', $kAsal->bagian()->pluck('id'))->pluck('id'))
                ->count();

            $res = $this->salin();
            $baru = PeriodeTracer::findOrFail($res['data']['id']);
            $kBaru = $baru->kuesioner()->firstOrFail();

            $idBagianBaru = $kBaru->bagian()->pluck('id');
            $soalBaru = Pertanyaan::whereIn('kuesioner_bagian_id', $idBagianBaru);

            $this->assertSame($jmlBagian, $kBaru->bagian()->count(), 'jumlah bagian berbeda');
            $this->assertSame($jmlSoal, $soalBaru->count(), 'jumlah pertanyaan berbeda');
            $this->assertSame(
                $jmlOpsi,
                DB::table('pertanyaan_opsi')->whereIn('pertanyaan_id', $soalBaru->pluck('id'))->count(),
                'jumlah opsi berbeda'
            );

            // Tidak ada angka mutlak di sini: yang dibuktikan test ini adalah
            // salinan sama dengan asalnya, dan itu sudah dijamin ketiga
            // perbandingan di atas. Mematok jumlah hanya akan membuat test
            // gagal setiap kali kuesioner sumber ditambah pertanyaan.
            $this->assertGreaterThan(0, $kBaru->bagian()->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_percabangan_menunjuk_salinan_bukan_asli(): void
    {
        DB::beginTransaction();
        try {
            $res = $this->salin();
            $baru = PeriodeTracer::findOrFail($res['data']['id']);
            $idBagianBaru = $baru->kuesioner()->firstOrFail()->bagian()->pluck('id');

            $soalBaru = Pertanyaan::whereIn('kuesioner_bagian_id', $idBagianBaru)->get()->keyBy('kode');
            $idBaru = $soalBaru->pluck('id')->all();

            $bersyarat = $soalBaru->whereNotNull('tampil_jika_pertanyaan_id');

            // Dibandingkan dengan kuesioner asal, bukan angka tetap: yang
            // penting seluruh percabangan ikut tersalin, berapa pun jumlahnya.
            $idBagianAsal = $this->asal()->kuesioner()->firstOrFail()->bagian()->pluck('id');
            $jmlAsal = Pertanyaan::whereIn('kuesioner_bagian_id', $idBagianAsal)
                ->whereNotNull('tampil_jika_pertanyaan_id')
                ->count();

            $this->assertCount($jmlAsal, $bersyarat, 'jumlah percabangan tersalin berbeda');

            foreach ($bersyarat as $p) {
                // pemicu wajib berada di dalam kumpulan salinan
                $this->assertContains(
                    $p->tampil_jika_pertanyaan_id,
                    $idBaru,
                    $p->kode . ' masih menunjuk pertanyaan periode asal'
                );

                // semua opsi pemicu wajib milik pertanyaan pemicu salinan
                $opsiPemicu = DB::table('pertanyaan_tampil_opsi')
                    ->where('pertanyaan_id', $p->id)
                    ->pluck('pertanyaan_opsi_id');

                $this->assertNotEmpty($opsiPemicu, $p->kode . ' tersalin tanpa opsi pemicu');

                $this->assertSame(
                    0,
                    DB::table('pertanyaan_opsi')
                        ->whereIn('id', $opsiPemicu)
                        ->where('pertanyaan_id', '!=', $p->tampil_jika_pertanyaan_id)
                        ->count(),
                    $p->kode . ' opsi pemicunya tidak cocok'
                );
            }

            // f502 harus dipicu oleh f8 SALINAN dengan nilai 1
            $f502 = $soalBaru['f502'];
            $this->assertSame($soalBaru['f8']->id, $f502->tampil_jika_pertanyaan_id);
            $this->assertSame(['1'], $f502->nilaiPemicu());

            // dan f8 salinan bukan f8 asli
            $f8Asli = Pertanyaan::whereIn(
                'kuesioner_bagian_id',
                $this->asal()->kuesioner()->firstOrFail()->bagian()->pluck('id')
            )->where('kode', 'f8')->firstOrFail();

            $this->assertNotSame($f8Asli->id, $soalBaru['f8']->id);
        } finally {
            DB::rollBack();
        }
    }

    public function test_ringkasan_field_ikut_tersalin(): void
    {
        DB::beginTransaction();
        try {
            $res = $this->salin();
            $baru = PeriodeTracer::findOrFail($res['data']['id']);
            $idBagianBaru = $baru->kuesioner()->firstOrFail()->bagian()->pluck('id');

            $ringkasan = Pertanyaan::whereIn('kuesioner_bagian_id', $idBagianBaru)
                ->whereNotNull('ringkasan_field')
                ->pluck('ringkasan_field', 'kode')
                ->all();

            $this->assertSame('status_saat_ini', $ringkasan['f8'] ?? null);
            $this->assertSame('waktu_tunggu_bulan', $ringkasan['f502'] ?? null);
            $this->assertSame('pendapatan', $ringkasan['f505'] ?? null);
            $this->assertSame('kesesuaian_bidang', $ringkasan['f14'] ?? null);
        } finally {
            DB::rollBack();
        }
    }

    public function test_salinan_tidak_aktif_dan_tanpa_respon(): void
    {
        DB::beginTransaction();
        try {
            $res = $this->salin();
            $baru = PeriodeTracer::findOrFail($res['data']['id']);

            $this->assertFalse($baru->is_active, 'salinan tidak boleh langsung aktif');
            $this->assertSame(0, DB::table('respon')->where('periode_tracer_id', $baru->id)->count());
            $this->assertSame($this->admin()->id, $baru->created_by);
        } finally {
            DB::rollBack();
        }
    }

    public function test_periode_asal_tidak_berubah(): void
    {
        DB::beginTransaction();
        try {
            $asal = $this->asal();
            $kAsal = $asal->kuesioner()->firstOrFail();
            $potret = function () use ($kAsal) {
                $soal = Pertanyaan::whereIn('kuesioner_bagian_id', $kAsal->bagian()->pluck('id'))
                    ->orderBy('id')
                    ->get(['id', 'kode', 'tampil_jika_pertanyaan_id'])
                    ->toArray();

                $cabang = DB::table('pertanyaan_tampil_opsi')
                    ->whereIn('pertanyaan_id', array_column($soal, 'id'))
                    ->orderBy('pertanyaan_id')
                    ->orderBy('pertanyaan_opsi_id')
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();

                return [$soal, $cabang];
            };

            $sebelum = $potret();

            $this->salin();

            $this->assertSame($sebelum, $potret(), 'periode asal ikut berubah');
        } finally {
            DB::rollBack();
        }
    }

    public function test_nama_ganda_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/periode/' . $this->asal()->id . '/salin', [
                'nama' => 'Tracer Study 2021',
                'tahun_lulusan' => 2027,
                'tanggal_mulai' => '2027-01-01',
                'tanggal_selesai' => '2027-12-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');
    }

    public function test_tanggal_terbalik_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/periode/' . $this->asal()->id . '/salin', [
                'nama' => 'Salinan Tanggal Salah',
                'tahun_lulusan' => 2027,
                'tanggal_mulai' => '2027-12-31',
                'tanggal_selesai' => '2027-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_periode_tanpa_kuesioner_ditolak(): void
    {
        DB::beginTransaction();
        try {
            $kosong = PeriodeTracer::create([
                'nama' => 'Periode Kosong Uji',
                'tahun_lulusan' => 2028,
                'tanggal_mulai' => '2028-01-01',
                'tanggal_selesai' => '2028-12-31',
                'is_active' => false,
            ]);

            $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/periode/' . $kosong->id . '/salin', [
                    'nama' => 'Salinan Dari Kosong',
                    'tahun_lulusan' => 2029,
                    'tanggal_mulai' => '2029-01-01',
                    'tanggal_selesai' => '2029-12-31',
                ])
                ->assertStatus(422);
        } finally {
            DB::rollBack();
        }
    }

    public function test_prodi_ditolak(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->postJson('/admin/kuisoner/periode/' . $this->asal()->id . '/salin', [
                'nama' => 'Salinan Prodi',
                'tahun_lulusan' => 2027,
                'tanggal_mulai' => '2027-01-01',
                'tanggal_selesai' => '2027-12-31',
            ])
            ->assertStatus(401);
    }
}
