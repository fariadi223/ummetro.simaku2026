<?php

namespace App\Http\Controllers\admin;

use App\Services\CoaService;
use App\Services\Hierarki\HierarkiService;

/**
 * Chart of Accounts (ueu_tbl_rek1..6), padanan Admin::rekening* di v1.
 */
class CoaController extends HierarkiController
{
  public function __construct(private CoaService $coa)
  {
  }

  protected function service(): HierarkiService
  {
    return $this->coa;
  }

  protected function meta(): array
  {
    return [
      'route' => 'admin.coa',
      'judul' => 'Chart of Accounts',
      'sub' => 'Kode rekening 6 level untuk anggaran, jurnal, dan laporan keuangan.',
      'ikon' => 'ti ti-list-tree',
      'istilah' => 'rekening',
      'anak' => 'sub-akun',
      'file' => 'COA',
    ];
  }
}
