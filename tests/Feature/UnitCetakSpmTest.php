<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tombol & lembar "Cetak SPM" di Daftar SPM halaman Otorisasi SPP unit induk (spp/proses2 →
 * spm/detail/nomor v1). Hanya GET: tidak menulis ke DB.
 */
class UnitCetakSpmTest extends TestCase
{
  /** SPM disetujui WR II milik sub unit FF.* + akun unit induk FF.00, atau skip. */
  private function contoh(): array
  {
    $spm = DB::table('ueu_tbl_spp as s')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->where('s.create_spp', '1')->where('s.no_spm', '!=', '')
      ->where('s.status_spm', 'Disetujui')->where('s.verifikasi2', 'Disetujui')
      ->where('u.kode', 'not like', '%.00')
      ->orderByDesc('s.id_spp')
      ->first(['s.id_spp', 's.no_spm', 's.tahun', 'u.kode']);

    if (!$spm) {
      $this->markTestSkipped('Tidak ada SPM disetujui WR II.');
    }

    $ff = explode('.', $spm->kode)[0];
    $user = User::whereHas('unit', fn ($q) => $q->where('kode', $ff . '.00'))->where('wewenang', 'unit')->first();
    if (!$user) {
      $this->markTestSkipped("Tidak ada akun unit induk {$ff}.00.");
    }

    return [$spm, $user];
  }

  private function sebagaiInduk(User $user, string $tahun)
  {
    return $this->actingAs($user)->withSession(['wewenang' => 'unit', 'periode' => $tahun]);
  }

  public function test_daftar_spm_menampilkan_tombol_cetak(): void
  {
    [$spm, $user] = $this->contoh();

    $this->sebagaiInduk($user, $spm->tahun)
      ->get(route('unit.otorisasi.index'))
      ->assertOk()
      ->assertSee('Cetak SPM')
      ->assertSee(route('unit.otorisasi.spm.cetak', $spm->id_spp), false);
  }

  public function test_cetak_spm_sub_unit_sendiri(): void
  {
    [$spm, $user] = $this->contoh();

    $this->sebagaiInduk($user, $spm->tahun)
      ->get(route('unit.otorisasi.spm.cetak', $spm->id_spp))
      ->assertOk()
      ->assertSee('SURAT PERINTAH MEMBAYAR')
      ->assertSee($spm->no_spm);
  }

  /** Spesimen tanda tangan dari storage/app/public/images/specimen, lewat symlink public/storage. */
  public function test_cetak_spm_menampilkan_spesimen(): void
  {
    [$spm, $user] = $this->contoh();
    $ff = explode('.', $spm->kode)[0];

    foreach (["{$ff}.00", 'otorisator1', 'otorisator2'] as $nama) {
      if (!is_file(storage_path("app/public/images/specimen/{$nama}.png"))) {
        $this->markTestSkipped("Spesimen {$nama}.png belum ada.");
      }
    }

    $this->sebagaiInduk($user, $spm->tahun)
      ->get(route('unit.otorisasi.spm.cetak', $spm->id_spp))
      ->assertOk()
      ->assertSee("storage/images/specimen/{$ff}.00.png", false)
      ->assertSee('storage/images/specimen/otorisator1.png', false)
      ->assertSee('storage/images/specimen/otorisator2.png', false);
  }

  /** Tombol di daftar mencetak langsung (iframe + ?cetak=1); lembar tanpa tombol Cetak/Kembali. */
  public function test_cetak_langsung_dari_daftar(): void
  {
    [$spm, $user] = $this->contoh();
    $url = route('unit.otorisasi.spm.cetak', $spm->id_spp);

    $this->sebagaiInduk($user, $spm->tahun)->get(route('unit.otorisasi.index'))
      ->assertOk()->assertSee('data-cetak-langsung', false);

    $this->sebagaiInduk($user, $spm->tahun)->get($url . '?cetak=1')
      ->assertOk()->assertSee('window.print();', false)->assertDontSee('Kembali');

    $this->sebagaiInduk($user, $spm->tahun)->get($url)
      ->assertOk()->assertDontSee('window.print();', false);
  }

  public function test_cetak_spm_unit_lain_ditolak(): void
  {
    [$spm, $user] = $this->contoh();
    $ff = explode('.', $spm->kode)[0];

    $lain = DB::table('ueu_tbl_spp as s')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->where('s.tahun', $spm->tahun)->where('s.no_spm', '!=', '')->where('s.verifikasi2', 'Disetujui')
      ->where('u.kode', 'not like', $ff . '.%')
      ->value('s.id_spp');

    if (!$lain) {
      $this->markTestSkipped('Tidak ada SPM unit lain pada T.A yang sama.');
    }

    $this->sebagaiInduk($user, $spm->tahun)
      ->get(route('unit.otorisasi.spm.cetak', $lain))
      ->assertNotFound();
  }

  /** Regresi: cetak SPM Operator SPM memakai SppAnggaran::dataCetakSpm yang sama. */
  public function test_cetak_spm_operator_spm_tetap_jalan(): void
  {
    [$spm] = $this->contoh();
    $op = User::where('wewenang', 'operatorspm')->first() ?? $this->markTestSkipped('Tidak ada akun operatorspm.');

    $this->actingAs($op)->withSession(['wewenang' => 'operatorspm', 'periode' => $spm->tahun])
      ->get(route('adminspm.spm.cetak', $spm->id_spp))
      ->assertOk()
      ->assertSee($spm->no_spm);
  }
}
