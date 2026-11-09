{{--
  Peringatan form input jurnal dikunci karena periode login bukan periode berjalan (penjurnalan tidak dibuka).
  Parameter: $tahun (T.A login), $tahunBerjalan (?int, T.A penjurnalan terbuka), $apa (mis. 'jurnal umum').
--}}
<div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
  <i class="ti ti-lock mt-1"></i>
  <div>
    <strong>Form dikunci.</strong> Periode T.A {{ $tahun }}/{{ $tahun + 1 }} bukan periode berjalan dan penjurnalannya tidak dibuka,
    {{ $apa }} hanya bisa diinput pada periode berjalan{{ $tahunBerjalan ? ' (T.A ' . $tahunBerjalan . '/' . ($tahunBerjalan + 1) . ')' : '' }}.
    <a href="{{ route('akuntansi.periode') }}" class="alert-link">Ganti periode</a>
    <div class="small mt-1">Untuk menjurnal T.A ini, minta Administrator membuka penjurnalannya di Setting &rsaquo; Periode.</div>
  </div>
</div>
