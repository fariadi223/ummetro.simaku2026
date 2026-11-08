{{--
  Satu pertanyaan kuesioner.

  Dipakai bersama oleh pratinjau administrator dan halaman pengisian alumni,
  supaya keduanya tidak pernah berbeda bentuk kontrolnya.

  Parameter:
    $soal      Model Pertanyaan (beserta relasi opsi, pemicu, pemicuOpsi)
               pemicuOpsi boleh berisi beberapa opsi sekaligus
    $nilai     array nilai terpilih/terisi saat ini (default kosong)
    $pratinjau true untuk mode administrator: tampilkan kode & aturan cabang
    $jawaban   array kode => nilai seluruh pertanyaan (default kosong); dipakai
               pertanyaan kabupaten untuk tahu provinsi mana yang sedang dipilih
--}}
@php
  use App\Models\Negara;
  use App\Models\Wilayah;
  use App\Services\StrukturKuesioner;

  $nilai = $nilai ?? [];
  $pratinjau = $pratinjau ?? false;
  $jawaban = $jawaban ?? [];

  // Pilihan kabupaten/kota bergantung provinsi yang sedang dipilih. Yang
  // dicetak server hanya pilihan provinsi itu, supaya jawaban tersimpan tetap
  // utuh saat halaman dimuat ulang (mis. setelah validasi gagal); sisanya
  // diganti JS ketika provinsinya berubah.
  $kodeIndukWilayah = null;

  // Pilihan tipe acuan (negara/provinsi/kabupaten) datang dari tabel acuan,
  // bukan dari relasi opsi pertanyaan.
  $opsiAcuan = [];

  if ($soal->tipe === 'negara') {
    $opsiAcuan = Negara::daftar();
  } elseif ($soal->tipe === 'provinsi') {
    $opsiAcuan = Wilayah::provinsi();
  } elseif ($soal->tipe === 'kabupaten') {
    $kodeIndukWilayah = $soal->wilayahInduk->kode ?? null;
    $opsiAcuan = Wilayah::kabupaten($jawaban[$kodeIndukWilayah][0] ?? null);
  }

  // Nilai opsi pemicu dipakai JS untuk memutuskan tampil/sembunyi. Boleh
  // lebih dari satu: cukup salah satunya cocok.
  $pemicuKode = $soal->pemicu->kode ?? null;
  $pemicuNilai = $soal->nilaiPemicu();
  $bersyarat = $pemicuKode && $pemicuNilai !== [];

  $terisi = fn ($v) => in_array((string) $v, array_map('strval', $nilai), true);
  $isiTunggal = $nilai[0] ?? '';

  // Pertanyaan bertipe text yang isinya punya bentuk baku (email, nomor
  // telepon) memakai kontrol yang sesuai, supaya papan tombol ponsel dan
  // contoh isiannya cocok. Isinya tetap diperiksa ulang di server.
  $jenisIsi = StrukturKuesioner::jenisIsi($soal->kode);

  // Disusun di PHP supaya atribut class tidak kebagian spasi sisa directive.
  $kelas = 'kp-soal';
  if ($bersyarat) {
    $kelas .= ' kp-tersembunyi';
  }
  if ($errors->has($soal->kode)) {
    $kelas .= ' kp-salah';
  }
@endphp

<div class="{{ $kelas }}"
     data-kode="{{ $soal->kode }}"
     @if ($bersyarat)
     data-pemicu="{{ $pemicuKode }}"
     data-pemicu-nilai="{{ json_encode($pemicuNilai, JSON_UNESCAPED_UNICODE) }}"
     @endif>

  <div class="kp-teks">
    {{ $soal->teks }}
    @if ($soal->wajib)
    <span class="text-danger">*</span>
    @endif
    @if ($pratinjau)
    <span class="badge bg-label-secondary kp-kode kp-badge-kode d-none">{{ $soal->kode }}</span>
    @endif
  </div>

  @if ($bersyarat && $pratinjau)
  <div class="kp-cabang mb-2">
    <i class="ti ti-git-branch ti-xs"></i>
    Tampil bila {{ $pemicuKode }} = "{{ $soal->pemicuOpsi->pluck('label')->implode('" atau "') }}"
  </div>
  @endif

  @switch ($soal->tipe)

    @case ('text')
      @if ($jenisIsi === StrukturKuesioner::ISI_EMAIL)
      <input type="email" class="form-control" name="{{ $soal->kode }}"
             value="{{ $isiTunggal }}" inputmode="email" autocomplete="email"
             maxlength="165" placeholder="nama@contoh.com"
             @if ($soal->wajib && !$bersyarat) required @endif>
      @elseif ($jenisIsi === StrukturKuesioner::ISI_TELEPON)
      <input type="tel" class="form-control" name="{{ $soal->kode }}"
             value="{{ $isiTunggal }}" inputmode="tel" autocomplete="tel"
             maxlength="20" placeholder="081234567890"
             @if ($soal->wajib && !$bersyarat) required @endif>
      @else
      <input type="text" class="form-control" name="{{ $soal->kode }}"
             value="{{ $isiTunggal }}"
             placeholder="Jawaban Anda" @if ($soal->wajib && !$bersyarat) required @endif>
      @endif
      @break

    @case ('number')
      <input type="number" class="form-control" name="{{ $soal->kode }}"
             value="{{ $isiTunggal }}"
             inputmode="numeric" placeholder="0" @if ($soal->wajib && !$bersyarat) required @endif>
      @break

    @case ('date')
      <input type="date" class="form-control" name="{{ $soal->kode }}"
             value="{{ $isiTunggal }}"
             @if ($soal->wajib && !$bersyarat) required @endif>
      @break

    @case ('select')
      <select class="form-select" name="{{ $soal->kode }}"
              @if ($soal->wajib && !$bersyarat) required @endif>
        <option value="">— pilih —</option>
        @foreach ($soal->opsi as $opsi)
        <option value="{{ $opsi->nilai }}" @selected($terisi($opsi->nilai))>{{ $opsi->label }}</option>
        @endforeach
      </select>
      @break

    @case ('radio')
      <div class="kp-pilihan">
        @foreach ($soal->opsi as $opsi)
        <div class="form-check">
          <input class="form-check-input" type="radio"
                 name="{{ $soal->kode }}" value="{{ $opsi->nilai }}"
                 id="p-{{ $soal->id }}-{{ $opsi->id }}"
                 @checked($terisi($opsi->nilai))>
          <label class="form-check-label" for="p-{{ $soal->id }}-{{ $opsi->id }}">
            {{ $opsi->label }}
            @if ($pratinjau)
            <span class="kp-kode text-muted kp-badge-kode d-none">({{ $opsi->nilai }})</span>
            @endif
          </label>
        </div>
        @endforeach
      </div>
      @break

    @case ('checkbox')
      <div class="kp-pilihan">
        @foreach ($soal->opsi as $opsi)
        <div class="form-check">
          <input class="form-check-input" type="checkbox"
                 name="{{ $soal->kode }}[]" value="{{ $opsi->nilai }}"
                 id="p-{{ $soal->id }}-{{ $opsi->id }}"
                 @checked($terisi($opsi->nilai))>
          <label class="form-check-label" for="p-{{ $soal->id }}-{{ $opsi->id }}">
            {{ $opsi->label }}
            @if ($pratinjau)
            <span class="kp-kode text-muted kp-badge-kode d-none">({{ $opsi->nilai }})</span>
            @endif
          </label>
        </div>
        @endforeach
      </div>
      @break

    @case ('scale')
      <div class="kp-skala">
        @foreach ($soal->opsi as $opsi)
        <div class="form-check">
          <input class="form-check-input" type="radio"
                 name="{{ $soal->kode }}" value="{{ $opsi->nilai }}"
                 id="p-{{ $soal->id }}-{{ $opsi->id }}"
                 @checked($terisi($opsi->nilai))>
          <label class="form-check-label" for="p-{{ $soal->id }}-{{ $opsi->id }}">
            {{ $opsi->label }}
          </label>
        </div>
        @endforeach
      </div>
      @break

    @case ('negara')
      <select class="form-select" name="{{ $soal->kode }}"
              @if ($soal->wajib && !$bersyarat) required @endif>
        <option value="">&mdash; pilih negara &mdash;</option>
        @foreach ($opsiAcuan as $kode => $namaNegara)
        <option value="{{ $kode }}" @selected($terisi($kode))>{{ $namaNegara }}</option>
        @endforeach
      </select>
      @break

    @case ('provinsi')
      <select class="form-select" name="{{ $soal->kode }}" data-wilayah="provinsi"
              @if ($soal->wajib && !$bersyarat) required @endif>
        <option value="">&mdash; pilih provinsi &mdash;</option>
        @foreach ($opsiAcuan as $kode => $namaWilayah)
        <option value="{{ $kode }}" @selected($terisi($kode))>{{ $namaWilayah }}</option>
        @endforeach
      </select>
      @break

    @case ('kabupaten')
      {{-- Daftar kabupaten/kota seluruh provinsi, dikirim sekali per halaman
           supaya dropdown berantai tidak memanggil server tiap pergantian. --}}
      @once
      <script type="application/json" id="kp-wilayah-kabupaten">
        @json(Wilayah::kabupatenPerProvinsi())
      </script>
      @endonce

      <select class="form-select" name="{{ $soal->kode }}" data-wilayah="kabupaten"
              @if ($kodeIndukWilayah) data-wilayah-induk="{{ $kodeIndukWilayah }}" @endif
              @if ($soal->wajib && !$bersyarat) required @endif>
        <option value="">&mdash; pilih kabupaten/kota &mdash;</option>
        @foreach ($opsiAcuan as $kode => $namaWilayah)
        <option value="{{ $kode }}" @selected($terisi($kode))>{{ $namaWilayah }}</option>
        @endforeach
      </select>

      @if (!$kodeIndukWilayah)
      <div class="alert alert-danger mb-0 mt-2 py-2">
        <i class="ti ti-alert-triangle me-1"></i>
        @if ($pratinjau)
          Pertanyaan ini bertipe <strong>kabupaten</strong> tapi belum ditautkan ke
          pertanyaan provinsi, jadi pilihannya tidak bisa disaring.
        @else
          Pilihan kabupaten/kota belum bisa ditampilkan. Silakan lewati; hubungi
          program studi Anda bila ini menghalangi pengisian.
        @endif
      </div>
      @endif
      @break

    @default
      <div class="alert alert-warning mb-0 py-2">
        Tipe <strong>{{ $soal->tipe }}</strong> belum punya tampilan.
      </div>
  @endswitch

  @error($soal->kode)
  <div class="kp-pesan-salah">
    <i class="ti ti-alert-circle ti-xs me-1"></i>{{ $message }}
  </div>
  @enderror

  @if (in_array($soal->tipe, ['radio', 'checkbox', 'select', 'scale']) && $soal->opsi->isEmpty())
  <div class="alert alert-danger mb-0 py-2">
    <i class="ti ti-alert-triangle me-1"></i>
    @if ($pratinjau)
      Pertanyaan ini bertipe <strong>{{ $soal->tipe }}</strong> tapi belum punya opsi, jadi
      alumni tidak bisa menjawabnya.
    @else
      Pertanyaan ini belum punya pilihan jawaban. Silakan lewati; hubungi program
      studi Anda bila ini menghalangi pengisian.
    @endif
  </div>
  @endif
</div>
