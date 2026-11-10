<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Ekspor buku besar ke Excel, tata letak seperti Download::bukubesar / bukubesarPembatasan / bukubesarAll v1:
 * baris 1–3 judul (universitas, "BUKU BESAR TAHUN T/T+1", nama akun), header di baris 5, data mulai baris 6.
 * $angka = huruf kolom yang berisi nominal (diberi format ribuan).
 */
class BukuBesarExport implements FromArray, WithHeadings, WithCustomStartCell, WithEvents, ShouldAutoSize, WithColumnFormatting, WithTitle
{
  public function __construct(
    private array $judul,
    private array $kepala,
    private array $baris,
    private array $angka,
    private string $namaSheet = 'Buku Besar', // opsional; dipakai juga oleh laporan unit
  ) {
  }

  public function array(): array
  {
    return $this->baris;
  }

  public function headings(): array
  {
    return $this->kepala;
  }

  public function startCell(): string
  {
    return 'A5';
  }

  public function title(): string
  {
    return $this->namaSheet;
  }

  public function columnFormats(): array
  {
    return array_fill_keys($this->angka, '#,##0;-#,##0;0');
  }

  public function registerEvents(): array
  {
    return [
      AfterSheet::class => function (AfterSheet $e) {
        $s = $e->sheet->getDelegate();
        $akhir = chr(ord('A') + count($this->kepala) - 1);
        foreach (array_values($this->judul) as $i => $teks) {
          $r = $i + 1;
          $s->mergeCells("A{$r}:{$akhir}{$r}");
          $s->setCellValue("A{$r}", $teks);
          $s->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
          $s->getStyle("A{$r}")->getFont()->setBold($i < 2)->setSize($i === 0 ? 12 : 10);
        }
        $s->getStyle("A5:{$akhir}5")->getFont()->setBold(true);
        $s->getStyle("A5:{$akhir}" . (5 + count($this->baris)))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $s->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
      },
    ];
  }
}
