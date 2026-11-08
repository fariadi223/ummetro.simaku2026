<?php

namespace App\Http\Controllers\admin;

use App\Services\Hierarki\HierarkiService;
use App\Services\MakService;

/**
 * Mata Anggaran Kegiatan (ueu_tbl_mak1..4), padanan Admin::mata_anggaran* di v1.
 * v1 hanya mengizinkan ubah level 3-4; di sini keempat level dapat dikelola.
 */
class MakController extends HierarkiController
{
  public function __construct(private MakService $mak)
  {
  }

  protected function service(): HierarkiService
  {
    return $this->mak;
  }

  protected function meta(): array
  {
    return [
      'route' => 'admin.mak',
      'judul' => 'Mata Anggaran Kegiatan',
      'sub' => 'Kode MAK 4 level untuk pengelompokan kegiatan dan rencana anggaran unit.',
      'ikon' => 'ti ti-sitemap',
      'istilah' => 'MAK',
      'anak' => 'sub-MAK',
      'file' => 'MAK',
    ];
  }
}
