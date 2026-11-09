<?php

use App\Http\Controllers\admin\BiayaController;
use App\Http\Controllers\admin\CoaController;
use App\Http\Controllers\admin\MakController;
use App\Http\Controllers\admin\PaguController;
use App\Http\Controllers\admin\PeriodeController;
use App\Http\Controllers\admin\ReviewController;
use App\Http\Controllers\admin\SumberdanaController;
use App\Http\Controllers\admin\UnitController;
use App\Http\Controllers\admin\UserController;
use App\Http\Controllers\adminspm\SpmController;
use App\Http\Controllers\adminspm\VerifikasiController;
use App\Http\Controllers\akuntansi\ArusKasController;
use App\Http\Controllers\akuntansi\BukuBesarController;
use App\Http\Controllers\akuntansi\JurnalBkkController;
use App\Http\Controllers\akuntansi\JurnalUmumController;
use App\Http\Controllers\akuntansi\NeracaPercobaanController;
use App\Http\Controllers\akuntansi\PencairanController;
use App\Http\Controllers\akuntansi\PenghasilanKomprehensifController;
use App\Http\Controllers\akuntansi\PerubahanAsetNetoController;
use App\Http\Controllers\akuntansi\PeriodeController as AkuntansiPeriodeController;
use App\Http\Controllers\akuntansi\PosisiKeuanganController;
use App\Http\Controllers\akuntansi\PenyetoranController;
use App\Http\Controllers\akuntansi\RealisasiController;
use App\Http\Controllers\akuntansi\SaldoAwalController;
use App\Http\Controllers\akuntansi\SaldoUnitController;
use App\Http\Controllers\authentications\AuthController;
use App\Http\Controllers\kasir\NonAnggaranController;
use App\Http\Controllers\kasir\PeriodeController as KasirPeriodeController;
use App\Http\Controllers\kasir\RealisasiSpmController;
use App\Http\Controllers\otorisator\OtorisasiSpmController;
use App\Http\Controllers\pages\DashboardController;
use App\Http\Controllers\unit\KegiatanController;
use App\Http\Controllers\unit\KeluaranController;
use App\Http\Controllers\unit\OtorisasiSppController;
use App\Http\Controllers\unit\PeriodeController as UnitPeriodeController;
use App\Http\Controllers\unit\ProfilController;
use App\Http\Controllers\unit\RinciController;
use App\Http\Controllers\unit\SppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - SIMAKU UM Metro
|--------------------------------------------------------------------------
|
| Login mengikuti v1: halaman di "/" atau "/login", form dikirim AJAX ke
| "login/validation", lalu diarahkan ke area sesuai wewenang.
|
*/

Route::middleware('guest')->group(function () {
  Route::get('/', [AuthController::class, 'index']);
  Route::get('login', [AuthController::class, 'index'])->name('login');
  Route::post('login/validation', [AuthController::class, 'validation'])
    ->middleware('throttle:10,1')
    ->name('login.validation');
});

Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])
  ->middleware('auth')
  ->name('logout');

/*
| Area per wewenang (URL sama dengan v1). Untuk sementara semuanya
| menampilkan dashboard sederhana sampai modul masing-masing dipindahkan.
|
| url area    => role yang boleh masuk
*/
$area = [
  'admin' => ['admin'],
  'akuntansi' => ['akuntansi'],
  'penerimaan' => ['penerimaan'],
  'kasir' => ['kasir'],
  'penggajian' => ['op1', 'op2'],
  'reviewer' => ['review'],
  'adminspm' => ['operatorspm'],
  'dashboard' => ['otorisator1', 'otorisator2'],
  'adminfakultas' => ['operator'],
  'adminunit' => ['unit'],
  'unit' => ['unit'],
];

foreach ($area as $url => $roles) {
  Route::get($url, [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:' . implode(',', $roles)])
    ->name($url . '.index');
}

/*
| Unit kerja (wewenang 'unit': sub-unit di /unit, unit induk di /adminunit)
*/
Route::middleware(['auth', 'role:unit'])
  ->prefix('unit')
  ->name('unit.')
  ->group(function () {
    // Pengaturan > Visi & Misi (padanan unit/profile di v1)
    Route::get('profil', [ProfilController::class, 'edit'])->name('profil');
    Route::put('profil', [ProfilController::class, 'update'])->name('profil.update');

    // Proses Bisnis - Kegiatan (padanan unit/kegiatan di v1). ?mak=kode MAK1 memilih tab bidang.
    Route::prefix('kegiatan')->name('kegiatan.')->group(function () {
      Route::get('/', [KegiatanController::class, 'index'])->name('index');
      Route::get('tambah', [KegiatanController::class, 'create'])->name('create');
      Route::post('/', [KegiatanController::class, 'store'])->name('store');
      Route::get('{id}/ubah', [KegiatanController::class, 'edit'])->name('edit');
      Route::put('{id}', [KegiatanController::class, 'update'])->name('update');
      Route::delete('{id}', [KegiatanController::class, 'destroy'])->name('destroy');
      Route::post('{id}/selesai', [KegiatanController::class, 'selesai'])->name('selesai');
    })->whereNumber('id');

    // Rincian pendapatan/belanja kegiatan (padanan unit/rinci/{idkeg} di v1)
    Route::prefix('rinci/{idkeg}')->name('rinci.')->group(function () {
      Route::get('/', [RinciController::class, 'index'])->name('index');
      Route::get('cetak', [RinciController::class, 'cetak'])->name('cetak');
      Route::post('surplus', [RinciController::class, 'surplus'])->name('surplus');
      Route::get('tambah/{jenis}', [RinciController::class, 'create'])->name('create');
      Route::post('{jenis}', [RinciController::class, 'store'])->name('store');
      Route::get('item/{idrinci}/ubah', [RinciController::class, 'edit'])->name('edit');
      Route::put('item/{idrinci}', [RinciController::class, 'update'])->name('update');
      Route::delete('item/{idrinci}', [RinciController::class, 'destroy'])->name('destroy');
    })->whereNumber(['idkeg', 'idrinci'])->where('jenis', 'pendapatan|belanja');

    // Keluaran (padanan unit/infopagu, rekap_bebasis_fungsi, rekap_bebasis_sifat di v1)
    Route::prefix('keluaran')->name('keluaran.')->group(function () {
      Route::get('informasi-pagu', [KeluaranController::class, 'infoPagu'])->name('pagu');
      Route::get('rekap-fungsi', [KeluaranController::class, 'rekapFungsi'])->name('fungsi');
      Route::get('rekap-fungsi/cetak', [KeluaranController::class, 'cetakFungsi'])->name('fungsi.cetak');
      Route::get('rekap-sifat', [KeluaranController::class, 'rekapSifat'])->name('sifat');
      Route::get('rekap-sifat/cetak', [KeluaranController::class, 'cetakSifat'])->name('sifat.cetak');
    });

    // Pengajuan SPP (padanan spp/proses1, spp/ajukan, spp/batal, spp/detail di v1)
    Route::prefix('spp')->name('spp.')->group(function () {
      Route::get('/', [SppController::class, 'index'])->name('index');
      Route::get('ajukan/{idkeg}', [SppController::class, 'create'])->name('create');
      Route::post('ajukan/{idkeg}', [SppController::class, 'store'])->name('store');
      Route::post('proses', [SppController::class, 'proses'])->name('proses');
      Route::delete('item/{id}', [SppController::class, 'batalKegiatan'])->name('item.destroy');
      Route::delete('{id}', [SppController::class, 'batalSpp'])->name('destroy');
      Route::get('{id}/cetak', [SppController::class, 'cetak'])->name('cetak');
    })->whereNumber(['idkeg', 'id']);

    // Otorisasi SPP oleh pimpinan unit induk (padanan spp/proses2, spp/nomor, spp/setujui, spp/tolak di v1)
    Route::prefix('otorisasi-spp')->name('otorisasi.')->group(function () {
      Route::get('/', [OtorisasiSppController::class, 'index'])->name('index');
      Route::get('{id}', [OtorisasiSppController::class, 'show'])->name('show');
      Route::get('{id}/cetak', [OtorisasiSppController::class, 'cetak'])->name('cetak');
      Route::get('spm/{id}/cetak', [OtorisasiSppController::class, 'cetakSpm'])->name('spm.cetak');
      Route::post('{id}/setujui', [OtorisasiSppController::class, 'setujui'])->name('setujui');
      Route::post('{id}/tolak', [OtorisasiSppController::class, 'tolak'])->name('tolak');
    })->whereNumber('id');

    // Pengaturan > Periode Anggaran (padanan unit/periode di v1)
    Route::get('periode', [UnitPeriodeController::class, 'index'])->name('periode');
    Route::post('periode', [UnitPeriodeController::class, 'pilih'])->name('periode.pilih');
  });

/*
| Otorisator SPM, area /dashboard: otorisator1 = BAK (Kepala Bagian Keuangan),
| otorisator2 = WR II (Wakil Rektor 2). Level ditentukan dari role yang login.
*/
Route::middleware(['auth', 'role:otorisator1,otorisator2'])
  ->prefix('dashboard/otorisasi-spm')
  ->name('otorisator.spm.')
  ->group(function () {
    // Padanan dashboard/otorisasi_one|two, spm/otorisasi/one|two, spm/approve/one|two, otoris_one|two_tolak di v1
    Route::get('/', [OtorisasiSpmController::class, 'index'])->name('index');
    Route::get('{id}', [OtorisasiSpmController::class, 'show'])->name('show');
    Route::post('{id}/setujui', [OtorisasiSpmController::class, 'setujui'])->name('setujui');
    Route::post('{id}/tolak', [OtorisasiSpmController::class, 'tolak'])->name('tolak');
  })->whereNumber('id');

// Daftar & cetak SPM disetujui untuk otorisator (padanan dashboard/spm & spm/detail/nomor di v1)
Route::middleware(['auth', 'role:otorisator1,otorisator2'])
  ->prefix('dashboard/spm')
  ->name('otorisator.daftar-spm.')
  ->group(function () {
    Route::get('/', [SpmController::class, 'index'])->name('index');
    Route::get('{id}/cetak', [SpmController::class, 'cetak'])->name('cetak');
  })->whereNumber('id');

/*
| Operator SPM / Pembuat SPM (wewenang 'operatorspm', area /adminspm)
*/
Route::middleware(['auth', 'role:operatorspm'])
  ->prefix('adminspm')
  ->name('adminspm.')
  ->group(function () {
    // Verifikasi SPP & pembuatan SPM (padanan adminspm/verifikasi, spp/verifikasi/* di v1)
    Route::prefix('verifikasi')->name('verifikasi.')->group(function () {
      Route::get('/', [VerifikasiController::class, 'index'])->name('index');
      Route::get('spp/{id}', [VerifikasiController::class, 'show'])->name('show');
      Route::post('spp/{id}/setujui', [VerifikasiController::class, 'setujui'])->name('setujui');
      Route::post('spp/{id}/tolak', [VerifikasiController::class, 'tolak'])->name('tolak');
      Route::get('spm/{id}', [SpmController::class, 'cetak'])->name('spm');
      Route::post('spm/{id}/batal', [VerifikasiController::class, 'batal'])->name('batal');
    })->whereNumber('id');

    // Daftar & cetak SPM disetujui (padanan adminspm/list_spm, spm/detail/nomor di v1)
    Route::prefix('spm')->name('spm.')->group(function () {
      Route::get('/', [SpmController::class, 'index'])->name('index');
      Route::get('{id}/cetak', [SpmController::class, 'cetak'])->name('cetak');
    })->whereNumber('id');
  });

/*
| Akuntansi (admin berpassword 'akuntansi' di v1, area /akuntansi)
*/
Route::middleware(['auth', 'role:akuntansi'])
  ->prefix('akuntansi')
  ->name('akuntansi.')
  ->group(function () {
    // Akuntansi Anggaran > Penyetoran Kas Unit (padanan akuntansi/penyetoran, penyetoran_input/proses/simpan di v1).
    // {ff} = kode unit induk dua digit (unit "FF.00").
    Route::prefix('penyetoran')->name('penyetoran.')->group(function () {
      Route::get('/', [PenyetoranController::class, 'index'])->name('index');
      Route::get('{ff}', [PenyetoranController::class, 'create'])->name('create');
      Route::post('{ff}', [PenyetoranController::class, 'store'])->name('store');
    })->where('ff', '[0-9]{2}');

    // Akuntansi Anggaran > Pencairan Anggaran Belanja (padanan akuntansi/spm_real, spm_jurnal di v1).
    Route::prefix('pencairan')->name('pencairan.')->group(function () {
      Route::get('/', [PencairanController::class, 'index'])->name('index');
      Route::post('{id}/jurnal', [PencairanController::class, 'jurnal'])->name('jurnal');
    })->whereNumber('id');

    // Akuntansi Anggaran > Realisasi Anggaran (padanan akuntansi/anggaran_search, anggaran_real, jurnal_unit*, jurnal_univ* di v1).
    Route::prefix('realisasi')->name('realisasi.')->group(function () {
      Route::get('/', [RealisasiController::class, 'index'])->name('index');
      Route::post('{spp}/{rinci}', [RealisasiController::class, 'store'])->name('store');
    })->whereNumber(['spp', 'rinci']);

    // Kertas Kerja > Jurnal Umum (padanan akuntansi/jurnalumum, jurnalumum_proses di v1).
    Route::prefix('jurnal-umum')->name('jurnal-umum.')->group(function () {
      Route::get('/', [JurnalUmumController::class, 'create'])->name('create');
      Route::post('/', [JurnalUmumController::class, 'store'])->name('store');
    });

    // Kertas Kerja > Jurnal Non Anggaran (padanan akuntansi/jurnalbkk, jurnalbkk_tabel/_json/_proses/_hapus di v1).
    Route::prefix('jurnal-bkk')->name('jurnal-bkk.')->group(function () {
      Route::get('/', [JurnalBkkController::class, 'index'])->name('index');
      Route::post('{id}', [JurnalBkkController::class, 'store'])->name('store');
      Route::delete('{id}', [JurnalBkkController::class, 'destroy'])->name('destroy');
    })->whereNumber('id');

    // Kertas Kerja > Saldo Anggaran Unit (padanan akuntansi/daftarsaldo_unit di v1).
    Route::get('saldo-unit', [SaldoUnitController::class, 'index'])->name('saldo-unit.index');

    // Kertas Kerja > Saldo Awal (padanan saldo/saldoawal & saldo/saldoawal/jurnalSaldo di v1).
    Route::get('saldo-awal', [SaldoAwalController::class, 'index'])->name('saldo-awal.index');
    Route::post('saldo-awal', [SaldoAwalController::class, 'store'])->name('saldo-awal.store');

    // Laporan Keuangan > Buku Besar (padanan akuntansi/bukubesar*, download/bukubesar* di v1).
    Route::prefix('buku-besar')->name('buku-besar.')->group(function () {
      Route::get('/', [BukuBesarController::class, 'index'])->name('index');
      Route::get('semua', [BukuBesarController::class, 'semua'])->name('semua');
      Route::get('{idrek6}', [BukuBesarController::class, 'detail'])->name('detail');
      Route::get('{idrek6}/excel', [BukuBesarController::class, 'excel'])->name('excel');
      Route::get('{idrek6}/kelompok', [BukuBesarController::class, 'kelompok'])->name('kelompok');
    })->whereNumber('idrek6');

    // Laporan Keuangan > Neraca Percobaan (padanan report/neraca/percobaan di v1).
    Route::get('neraca-percobaan', [NeracaPercobaanController::class, 'index'])->name('neraca-percobaan.index');

    // Laporan Keuangan > Laporan Posisi Keuangan (padanan report/neraca/view di v1).
    Route::get('posisi-keuangan', [PosisiKeuanganController::class, 'index'])->name('posisi-keuangan.index');
    Route::get('posisi-keuangan/cetak', [PosisiKeuanganController::class, 'cetak'])->name('posisi-keuangan.cetak');
    Route::get('posisi-keuangan/komparasi', [PosisiKeuanganController::class, 'komparasi'])->name('posisi-keuangan.komparasi');

    // Laporan Keuangan > Penghasilan Komprehensif (padanan report/aktivitas/view di v1).
    Route::get('penghasilan-komprehensif', [PenghasilanKomprehensifController::class, 'index'])->name('penghasilan-komprehensif.index');

    // Laporan Keuangan > Perubahan Aset Neto (padanan report/asetneto/view di v1).
    Route::get('perubahan-aset-neto', [PerubahanAsetNetoController::class, 'index'])->name('perubahan-aset-neto.index');

    // Laporan Keuangan > Arus Kas (padanan report/aruskas/view di v1).
    Route::get('arus-kas', [ArusKasController::class, 'index'])->name('arus-kas.index');

    // Periode anggaran (session 'periode'), padanan akuntansi/periode di v1.
    Route::get('periode', [AkuntansiPeriodeController::class, 'index'])->name('periode');
    Route::post('periode', [AkuntansiPeriodeController::class, 'pilih'])->name('periode.pilih');
  });

/*
| Kasir (wewenang 'kasir', area /kasir)
*/
Route::middleware(['auth', 'role:kasir'])
  ->prefix('kasir/spm')
  ->name('kasir.spm.')
  ->group(function () {
    // Realisasi SPM (padanan kasir/spm, tunai, transfer, transfer_proses, lihat, paraf_spm di v1)
    Route::get('/', [RealisasiSpmController::class, 'index'])->name('index');
    Route::post('{id}/tunai', [RealisasiSpmController::class, 'tunai'])->name('tunai');
    Route::get('{id}/transfer', [RealisasiSpmController::class, 'transfer'])->name('transfer');
    Route::post('{id}/transfer', [RealisasiSpmController::class, 'simpanTransfer'])->name('transfer.simpan');
    Route::get('{id}/bkk', [RealisasiSpmController::class, 'bkk'])->name('bkk');
    Route::get('{id}/penerima', [RealisasiSpmController::class, 'paraf'])->name('paraf');
    Route::post('{id}/penerima', [RealisasiSpmController::class, 'simpanParaf'])->name('paraf.simpan');
  })->whereNumber('id');

// Periode anggaran kasir (padanan kasir/periode di v1)
Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
  Route::get('periode', [KasirPeriodeController::class, 'index'])->name('periode');
  Route::post('periode', [KasirPeriodeController::class, 'pilih'])->name('periode.pilih');
});

// BKK non anggaran (padanan kasir/input_non_anggaran, simpan_non_anggaran, non_anggaran, bkk_tunai, bkk_hapus, bkk_tunai_cp di v1)
Route::middleware(['auth', 'role:kasir'])
  ->prefix('kasir/non-anggaran')
  ->name('kasir.non-anggaran.')
  ->group(function () {
    Route::get('/', [NonAnggaranController::class, 'index'])->name('index');
    Route::get('input', [NonAnggaranController::class, 'create'])->name('create');
    Route::post('/', [NonAnggaranController::class, 'store'])->name('store');
    Route::post('{id}/tunai', [NonAnggaranController::class, 'tunai'])->name('tunai');
    Route::get('{id}/cetak', [NonAnggaranController::class, 'cetak'])->name('cetak');
    Route::delete('{id}', [NonAnggaranController::class, 'destroy'])->name('destroy');
  })->whereNumber('id');

/*
| Administrator
*/
Route::middleware(['auth', 'role:admin'])
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    // Data kode berjenjang: {level} = level data, {id} = id-nya.
    //   coa : Chart of Accounts      (ueu_tbl_rek1..6)
    //   mak : Mata Anggaran Kegiatan (ueu_tbl_mak1..4)
    foreach (['coa' => [CoaController::class, 6], 'mak' => [MakController::class, 4]] as $nama => [$ctl, $max]) {
      Route::prefix($nama)->name("{$nama}.")->group(function () use ($ctl) {
        Route::get('/', [$ctl, 'index'])->name('index');
        Route::get('cari', [$ctl, 'cari'])->name('cari');
        Route::get('export', [$ctl, 'export'])->name('export');
        Route::get('cetak', [$ctl, 'cetak'])->name('cetak');
        Route::post('{level}', [$ctl, 'store'])->name('store');
        Route::get('{level}/{id}', [$ctl, 'show'])->name('show');
        Route::put('{level}/{id}', [$ctl, 'update'])->name('update');
        Route::delete('{level}/{id}', [$ctl, 'destroy'])->name('destroy');
        Route::post('{level}/{id}/salin', [$ctl, 'salin'])->name('salin');
      })->where(['level' => "[1-{$max}]", 'id' => '[0-9]+']);
    }

    // Kode sumber dana (ueu_tbl_sumberdana)
    Route::prefix('sumberdana')->name('sumberdana.')->group(function () {
      Route::get('/', [SumberdanaController::class, 'index'])->name('index');
      Route::post('/', [SumberdanaController::class, 'store'])->name('store');
      Route::put('{sumberdana}', [SumberdanaController::class, 'update'])->name('update');
      Route::delete('{sumberdana}', [SumberdanaController::class, 'destroy'])->name('destroy');
    })->whereNumber('sumberdana');

    // Setting > Periode (ueu_tbl_info + session periode)
    Route::prefix('periode')->name('periode.')->group(function () {
      Route::get('/', [PeriodeController::class, 'index'])->name('index');
      Route::post('pilih', [PeriodeController::class, 'pilih'])->name('pilih');
      Route::put('{info}/jadwal', [PeriodeController::class, 'jadwal'])->whereNumber('info')->name('jadwal');
      Route::put('{info}/jurnal', [PeriodeController::class, 'jurnal'])->whereNumber('info')->name('jurnal');
    });

    // Setting > Pagu (ueu_tbl_pagu, ueu_tbl_pagu_unit)
    Route::prefix('pagu')->name('pagu.')->group(function () {
      Route::get('/', [PaguController::class, 'index'])->name('index');
      Route::post('sumber', [PaguController::class, 'storeSumber'])->name('sumber.store');
      Route::put('sumber/{pagu}', [PaguController::class, 'updateSumber'])->where('pagu', '[A-Za-z0-9]+')->name('sumber.update');
      Route::post('unit', [PaguController::class, 'storeUnit'])->name('unit.store');
      Route::put('unit/{paguUnit}', [PaguController::class, 'updateUnit'])->whereNumber('paguUnit')->name('unit.update');
    });

    // Setting > Penetapan Review (tbl_review)
    Route::prefix('review')->name('review.')->group(function () {
      Route::get('/', [ReviewController::class, 'index'])->name('index');
      Route::post('tetapkan', [ReviewController::class, 'tetapkan'])->name('tetapkan');
      Route::post('lepas', [ReviewController::class, 'lepas'])->name('lepas');
      Route::post('salin', [ReviewController::class, 'salin'])->name('salin');
      Route::delete('{review}', [ReviewController::class, 'hapus'])->whereNumber('review')->name('hapus');
    });

    // Master (standar) biaya (tbl_biaya)
    Route::prefix('biaya')->name('biaya.')->group(function () {
      Route::get('/', [BiayaController::class, 'index'])->name('index');
      Route::post('/', [BiayaController::class, 'store'])->name('store');
      Route::get('cetak', [BiayaController::class, 'cetak'])->name('cetak');
      Route::put('{biaya}', [BiayaController::class, 'update'])->name('update');
      Route::delete('{biaya}', [BiayaController::class, 'destroy'])->name('destroy');
    })->whereNumber('biaya');

    // Unit kerja (ueu_tbl_unit)
    Route::prefix('unit')->name('unit.')->group(function () {
      Route::get('/', [UnitController::class, 'index'])->name('index');
      Route::post('/', [UnitController::class, 'store'])->name('store');
      Route::get('cetak', [UnitController::class, 'cetak'])->name('cetak');
      Route::get('{unit}', [UnitController::class, 'show'])->name('show');
      Route::put('{unit}', [UnitController::class, 'update'])->name('update');
      Route::patch('{unit}/klinik', [UnitController::class, 'klinik'])->name('klinik');
      Route::delete('{unit}', [UnitController::class, 'destroy'])->name('destroy');
    })->whereNumber('unit');

    // Akun pengguna (ueu_tbl_user)
    Route::prefix('user')->name('user.')->group(function () {
      Route::get('/', [UserController::class, 'index'])->name('index');
      Route::post('/', [UserController::class, 'store'])->name('store');
      Route::put('{user}', [UserController::class, 'update'])->name('update');
      Route::put('{user}/password', [UserController::class, 'password'])->name('password');
      Route::post('{user}/lihat-password', [UserController::class, 'lihatPassword'])
        ->middleware('throttle:30,1')
        ->name('lihat-password');
      Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    })->whereNumber('user');
  });
