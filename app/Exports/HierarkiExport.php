<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor data berjenjang (COA, MAK). Kolom mengikuti Download::akun_rekening
 * di v1: NO | NOMOR | LEVEL-1 .. LEVEL-N (nama ditaruh di kolom levelnya).
 */
class HierarkiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
  private int $no = 0;

  public function __construct(private Collection $baris, private int $levelMax)
  {
  }

  public function collection(): Collection
  {
    return $this->baris;
  }

  public function headings(): array
  {
    return array_merge(['NO', 'NOMOR'], array_map(fn ($l) => "LEVEL-{$l}", range(1, $this->levelMax)));
  }

  public function map($r): array
  {
    $kolom = array_fill(0, $this->levelMax, '');
    $kolom[$r->level - 1] = $r->nama;

    return array_merge([++$this->no, $r->kode_lengkap], $kolom);
  }

  public function columnFormats(): array
  {
    // Kode seperti "1.1.01" harus tetap teks, bukan angka/tanggal.
    return ['B' => NumberFormat::FORMAT_TEXT];
  }

  public function styles(Worksheet $sheet): array
  {
    return [1 => ['font' => ['bold' => true]]];
  }
}
