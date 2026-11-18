<?php

namespace App\Http\Middleware;

use App\Models\Info;
use App\Services\ReviewKegiatan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route reviewer berawalan /reviewer/kegiatan/{kode}: memastikan unit {kode} ditetapkan ke reviewer
 * yang login pada T.A sesi (tbl_review), lalu
 *   - menyimpan unit di atribut request 'unit_review' (dibaca controller lewat konteks()),
 *   - melepas parameter {kode} dari route, sehingga method controller unit (KegiatanController,
 *     RinciController) yang dipakai ulang tetap menerima parameter yang sama ($idkeg, $jenis, ...),
 *   - menjadikan {kode} nilai bawaan route(), sehingga view cukup memanggil route('reviewer.rinci.index', $idkeg).
 */
class ReviewerUnit
{
  public function __construct(private ReviewKegiatan $review)
  {
  }

  public function handle(Request $request, Closure $next): Response
  {
    $kode = (string) $request->route('kode');
    $tahun = (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']));
    $unit = $this->review->unitDitugaskan((int) $request->user()->idlog, $tahun, $kode);

    if (!$unit) {
      return redirect()->route('reviewer.unit.index')
        ->with('error', "Unit {$kode} tidak ditetapkan kepada Anda untuk T.A {$tahun}/" . ($tahun + 1) . '.');
    }

    $request->attributes->set('unit_review', $unit);
    $request->attributes->set('tahun_review', $tahun);
    $request->route()->forgetParameter('kode');
    URL::defaults(['kode' => $unit->kode]);

    return $next($request);
  }
}
