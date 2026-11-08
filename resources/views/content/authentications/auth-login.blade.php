@php
  $tema = asset('themes_v1/assets');
  $dokumen = [
    ['Formulir Rencana Kegiatan Unit Kerja.docx', 'Formulir Rencana Kegiatan Unit Kerja', 'ti-file-text'],
    ['Formulir Rencana Strategik Unit Kerja.docx', 'Formulir Rencana Strategik Unit Kerja', 'ti-file-text'],
    ['Laporan pertanggungjawaban dan capaian anggaran.xlsx', 'Laporan Pertanggungjawaban dan Capaian Anggaran', 'ti-file-spreadsheet'],
    ['STOK_removed_merged.pdf', 'Struktur Organisasi dan Tata Kelola (SOTK)', 'ti-file-description'],
  ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index,follow">
  <meta name="description" content="Sistem Informasi Manajemen dan Akuntansi Keuangan (SIMAKU) Universitas Muhammadiyah Metro">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Masuk | {{ $sim }} UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}">
  <style>
    :root {
      --primary-rgb: 115, 103, 240;           /* primary v2 (#7367f0) */
      --primary: rgb(var(--primary-rgb));
      --primary-dark: #4338b8;
      --bg: #f4f5fb;
      --card: #ffffff;
      --ink: #2f2b3d;
      --ink-2: #6d6b77;
      --ink-3: #9a98a6;
      --line: #e6e6ee;
      --field: #ffffff;
      --danger: #e5484d;
      --danger-bg: #fdecec;
      --radius: 14px;
      --gelap: 22, 16, 64;                     /* lapisan penggelap gradien brand (indigo tua) */
    }
    @media (prefers-color-scheme: dark) {
      :root {
        --bg: #1c1d2a; --card: #25263a; --ink: #e8e7f2; --ink-2: #b4b3c4; --ink-3: #8c8ba0;
        --line: #3a3b55; --field: #1f2033; --danger-bg: rgba(229, 72, 77, .14);
      }
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0; font-family: "Public Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
      color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased;
    }
    a { color: var(--primary); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* Satu gradien brand utuh di belakang kedua panel (kiri & kanan sinkron). */
    .layout {
      min-height: 100%; display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
      background: radial-gradient(circle at 100% 0%, rgba(255, 255, 255, .2), transparent 40%),
                  linear-gradient(135deg, var(--primary) 0%, #5a4fd6 55%, var(--primary-dark) 100%);
      position: relative; isolation: isolate;
    }
    /* Siger di luar panel: selebar seluruh halaman, menempel di bawah, di belakang kedua panel.
       siger-login.png = sigerbg_1.png dipotong rapat (tanpa margin transparan). */
    .layout::before {
      content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
      background: url("{{ asset('assets/img/illustrations/siger-login.png') }}") center bottom / 100% auto no-repeat;
      opacity: .12; filter: brightness(0) invert(1);
    }
    .layout > .brand, .layout > .panel { z-index: 1; }

    /* Panel brand (kiri) */
    .brand {
      position: relative; overflow: hidden; color: #fff; padding: 48px 56px;
      display: flex; flex-direction: column; justify-content: flex-start; gap: 32px;
    }
    /* Blok judul naik: jarak tetap dari logo, footer didorong ke bawah. */
    .brand-isi { margin-top: clamp(32px, 9vh, 96px); }
    .brand-foot { margin-top: auto; }
    .brand::after {                            /* cahaya lembut */
      content: ""; position: absolute; width: 520px; height: 520px; left: -160px; top: 38%;
      background: radial-gradient(circle, rgba(255, 255, 255, .14), transparent 65%); pointer-events: none;
    }
    .brand > * { position: relative; z-index: 1; }
    .brand-top { display: flex; align-items: center; gap: 14px; }
    .brand-top img { width: 52px; height: 52px; background: rgba(255, 255, 255, .95); border-radius: 14px; padding: 5px; }
    .brand-top .nama { font-weight: 700; font-size: 1.05rem; line-height: 1.2; }
    .brand-top .sub { font-size: .82rem; opacity: .8; }
    .brand h1 { margin: 0 0 14px; font-size: clamp(2.2rem, 3.6vw, 3.2rem); font-weight: 800; letter-spacing: -.02em; line-height: 1.05; }
    .brand .lead { margin: 0; max-width: 40rem; font-size: 1.02rem; line-height: 1.6; opacity: .9; }
    .fitur { list-style: none; padding: 0; margin: 28px 0 0; display: grid; gap: 12px; max-width: 30rem; }
    .fitur li { display: flex; align-items: center; gap: 12px; font-size: .92rem; }
    .fitur i { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 1.1rem;
               background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .22); flex-shrink: 0; }
    .brand-foot { font-size: .8rem; opacity: .75; }

    /* Panel form (kanan) */
    /* Latar kanan = gradien brand yang sama (dari .layout), digelapkan bertahap dari sambungan
       (0 di tepi kiri, tanpa garis batas) ke kanan, ditambah pola titik putih samar. */
    .panel {
      position: relative; overflow: hidden; isolation: isolate;
      display: flex; align-items: center; justify-content: center; padding: 40px 24px;
      background: linear-gradient(90deg, rgba(var(--gelap), 0) 0%, rgba(var(--gelap), .12) 35%, rgba(var(--gelap), .18) 100%);
    }
    .panel::before {
      content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none;
      background-image: radial-gradient(rgba(255, 255, 255, .22) 1px, transparent 1.4px);
      background-size: 22px 22px;
      -webkit-mask-image: radial-gradient(ellipse 75% 70% at 50% 45%, transparent 35%, #000 85%);
              mask-image: radial-gradient(ellipse 75% 70% at 50% 45%, transparent 35%, #000 85%);
    }
    .kotak { width: 100%; max-width: 420px; }
    .kartu { background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 36px 32px;
             box-shadow: 0 30px 60px -28px rgba(20, 14, 80, .55); }
    .kartu h2 { margin: 0 0 6px; font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; }
    .kartu .intro { margin: 0 0 26px; color: var(--ink-2); font-size: .93rem; }

    .galat { display: none; gap: 10px; align-items: flex-start; padding: 12px 14px; margin-bottom: 18px; border-radius: 12px;
             background: var(--danger-bg); color: var(--danger); font-size: .88rem; line-height: 1.45; }
    .galat.tampil { display: flex; animation: geser .25s ease; }
    .galat i { font-size: 1.15rem; margin-top: 1px; }
    .galat strong { display: block; }

    .isian { margin-bottom: 16px; }
    .isian label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: 7px; color: var(--ink); }
    .kolom { position: relative; }
    .kolom > i.ikon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ink-3); font-size: 1.15rem; pointer-events: none; }
    .kolom input {
      width: 100%; height: 48px; padding: 0 44px; border-radius: 12px; border: 1px solid var(--line);
      background: var(--field); color: var(--ink); font: inherit; font-size: .95rem; transition: border-color .15s, box-shadow .15s;
    }
    .kolom input::placeholder { color: var(--ink-3); }
    .kolom input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(var(--primary-rgb), .16); }
    .kolom input[aria-invalid="true"] { border-color: var(--danger); box-shadow: 0 0 0 4px rgba(229, 72, 77, .12); }
    .kolom:focus-within > i.ikon { color: var(--primary); }
    .intip { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border: 0;
             border-radius: 9px; background: transparent; color: var(--ink-3); cursor: pointer; display: grid; place-items: center; font-size: 1.15rem; }
    .intip:hover { color: var(--ink); background: rgba(var(--primary-rgb), .08); }
    .intip:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
    .pesan-isian { display: none; margin-top: 6px; font-size: .8rem; color: var(--danger); }
    .isian.salah .pesan-isian { display: block; }
    .capslock { display: none; margin-top: 6px; font-size: .8rem; color: #b7791f; }
    .capslock.tampil { display: flex; gap: 6px; align-items: center; }

    .tombol {
      width: 100%; height: 50px; margin-top: 8px; border: 0; border-radius: 12px; cursor: pointer;
      font: inherit; font-weight: 600; font-size: 1rem; color: #fff; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      background: linear-gradient(135deg, var(--primary), #5a4fd6); box-shadow: 0 10px 22px -10px rgba(var(--primary-rgb), .7);
      transition: transform .12s ease, box-shadow .15s ease, filter .15s;
    }
    .tombol:hover { filter: brightness(1.05); box-shadow: 0 14px 26px -10px rgba(var(--primary-rgb), .75); }
    .tombol:active { transform: translateY(1px); }
    .tombol:focus-visible { outline: 3px solid rgba(var(--primary-rgb), .45); outline-offset: 2px; }
    .tombol[disabled] { cursor: progress; filter: saturate(.85); }
    .putar { width: 18px; height: 18px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .45); border-top-color: #fff; animation: putar .7s linear infinite; display: none; }
    .tombol.proses .putar { display: inline-block; }
    .tombol.proses .ti { display: none; }

    .dokumen { margin-top: 22px; }
    .dokumen h3 { margin: 0 0 10px; font-size: .74rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255, 255, 255, .75); }
    .dokumen ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
    .dokumen a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; color: #fff;
                 border: 1px solid transparent; transition: background .15s, border-color .15s; font-size: .88rem; line-height: 1.3; }
    .dokumen a:hover { text-decoration: none; background: rgba(255, 255, 255, .12); border-color: rgba(255, 255, 255, .22); }
    .dokumen a:focus-visible { outline: 2px solid #fff; outline-offset: 1px; }
    .dokumen a .ti:first-child { font-size: 1.3rem; color: #fff; opacity: .9; flex-shrink: 0; }
    .dokumen a .unduh { margin-left: auto; color: rgba(255, 255, 255, .7); font-size: 1.05rem; }
    .hak { margin-top: 22px; text-align: center; font-size: .78rem; color: rgba(255, 255, 255, .7); }

    @keyframes putar { to { transform: rotate(360deg); } }
    @keyframes geser { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
    @keyframes masuk { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .kotak { animation: masuk .45s ease both; }

    /* Layar kecil: panel brand menjadi banner ringkas di atas */
    @media (max-width: 991px) {
      .layout { grid-template-columns: 1fr; }
      .brand { padding: 28px 20px 64px; gap: 18px; }
      .layout::before { opacity: .1; }
      .brand h1 { font-size: 2rem; margin-bottom: 6px; }
      .brand-isi { display: none; }            /* judul, deskripsi & fitur disembunyikan di mobile */
      .brand .lead { font-size: .92rem; }
      .fitur, .brand-foot { display: none; }
      .panel { padding: 0 16px 32px; margin-top: -44px; position: relative; z-index: 2; align-items: flex-start;
               background: transparent; overflow: visible; }
      .panel::before { display: none; }
      .kartu { padding: 28px 22px; }
    }
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
  </style>
</head>
<body>
<div class="layout">

  <aside class="brand" aria-label="Tentang {{ $sim }}">
    <div class="brand-top">
      <img src="{{ $tema }}/images/logoummetro_3.png" alt="Logo Universitas Muhammadiyah Metro">
      <div>
        <div class="nama">Universitas Muhammadiyah Metro</div>
        <div class="sub">Sistem Informasi Manajemen Keuangan</div>
      </div>
    </div>

    <div class="brand-isi">
      <h1>{{ $sim }}</h1>
      <p class="lead">Sistem Informasi Manajemen dan Akuntansi Keuangan Universitas Muhammadiyah Metro — dari penyusunan anggaran unit, pengajuan SPP–SPM, pembayaran dan LPJ, hingga penjurnalan dan laporan keuangan.</p>
      <ul class="fitur">
        <li><i class="ti ti-list-check" aria-hidden="true"></i> Rencana kegiatan &amp; rincian anggaran unit</li>
        <li><i class="ti ti-send" aria-hidden="true"></i> Laporan Keuangan Program Studi & Unit Kerja</li>
        <li><i class="ti ti-file-certificate" aria-hidden="true"></i> Laporan Keuangan Universitas ISAK 35</li>
      </ul>
    </div>

    <div class="brand-foot">&copy; {{ date('Y') }} Universitas Muhammadiyah Metro</div>
  </aside>

  <main class="panel">
    <div class="kotak">
      <div class="kartu">
        <h2>Masuk ke akun Anda</h2>
        <p class="intro">Gunakan username dan password yang terdaftar di {{ $sim }}.</p>

        <div class="galat" id="galat" role="alert" aria-live="assertive">
          <i class="ti ti-alert-circle" aria-hidden="true"></i>
          <div><strong id="galat-judul">Akun tidak terdaftar</strong><span id="galat-isi">Pastikan username dan password yang dimasukkan benar.</span></div>
        </div>

        <form id="form_login" method="post" action="{{ url('login/validation') }}" novalidate>
          @csrf
          <div class="isian">
            <label for="username">Username</label>
            <div class="kolom">
              <i class="ti ti-user ikon" aria-hidden="true"></i>
              <input type="text" id="username" name="username" autocomplete="username" autocapitalize="off" spellcheck="false"
                     maxlength="32" placeholder="Masukkan username" aria-describedby="username-pesan" autofocus required>
            </div>
            <div class="pesan-isian" id="username-pesan">Username wajib diisi.</div>
          </div>

          <div class="isian">
            <label for="password">Password</label>
            <div class="kolom">
              <i class="ti ti-lock ikon" aria-hidden="true"></i>
              <input type="password" id="password" name="password" autocomplete="current-password"
                     maxlength="32" placeholder="Masukkan password" aria-describedby="password-pesan capslock" required>
              <button type="button" class="intip" id="intip" aria-label="Tampilkan password" aria-pressed="false">
                <i class="ti ti-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="pesan-isian" id="password-pesan">Password wajib diisi.</div>
            <div class="capslock" id="capslock"><i class="ti ti-letter-case-upper" aria-hidden="true"></i> Caps Lock aktif</div>
          </div>

          <button type="submit" class="tombol" id="tombol">
            <span class="putar" aria-hidden="true"></span>
            <i class="ti ti-login" aria-hidden="true"></i>
            <span id="tombol-teks">Masuk</span>
          </button>
        </form>
      </div>

      <section class="dokumen" aria-labelledby="judul-dokumen">
        <h3 id="judul-dokumen">Unduh dokumen</h3>
        <ul>
          @foreach ($dokumen as [$berkas, $judul, $ikon])
            <li>
              <a href="{{ asset('download/' . $berkas) }}" download>
                <i class="ti {{ $ikon }}" aria-hidden="true"></i>
                <span>{{ $judul }}</span>
                <i class="ti ti-download unduh" aria-hidden="true"></i>
              </a>
            </li>
          @endforeach
        </ul>
      </section>

      <p class="hak">{{ $sim }} · Universitas Muhammadiyah Metro</p>
    </div>
  </main>

</div>

<script>
(function () {
  // Kontrak sama dengan neon-login.js v1: POST login/validation -> { login_status: success|invalid, redirect_url }
  const form = document.getElementById('form_login');
  const tombol = document.getElementById('tombol');
  const teks = document.getElementById('tombol-teks');
  const galat = document.getElementById('galat');
  const galatJudul = document.getElementById('galat-judul');
  const galatIsi = document.getElementById('galat-isi');
  const user = document.getElementById('username');
  const pass = document.getElementById('password');
  const intip = document.getElementById('intip');
  const caps = document.getElementById('capslock');

  const tandai = (input, salah) => {
    input.setAttribute('aria-invalid', salah ? 'true' : 'false');
    input.closest('.isian').classList.toggle('salah', salah);
  };
  const tampilGalat = (judul, isi) => {
    galatJudul.textContent = judul;
    galatIsi.textContent = isi;
    galat.classList.remove('tampil'); void galat.offsetWidth; galat.classList.add('tampil');
  };
  const sibuk = (ya) => {
    tombol.disabled = ya;
    tombol.classList.toggle('proses', ya);
    teks.textContent = ya ? 'Memeriksa…' : 'Masuk';
  };

  [user, pass].forEach(i => i.addEventListener('input', () => { if (i.value.trim()) tandai(i, false); }));

  intip.addEventListener('click', () => {
    const lihat = pass.type === 'password';
    pass.type = lihat ? 'text' : 'password';
    intip.setAttribute('aria-pressed', String(lihat));
    intip.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Tampilkan password');
    intip.querySelector('i').className = 'ti ' + (lihat ? 'ti-eye-off' : 'ti-eye');
    pass.focus();
  });

  ['keydown', 'keyup'].forEach(ev => pass.addEventListener(ev, e => {
    if (e.getModifierState) caps.classList.toggle('tampil', e.getModifierState('CapsLock'));
  }));
  pass.addEventListener('blur', () => caps.classList.remove('tampil'));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const kosongU = !user.value.trim(), kosongP = !pass.value;
    tandai(user, kosongU); tandai(pass, kosongP);
    if (kosongU || kosongP) { (kosongU ? user : pass).focus(); return; }

    galat.classList.remove('tampil');
    sibuk(true);
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: new FormData(form),
        credentials: 'same-origin',
      });
      if (res.status === 419) {
        sibuk(false);
        tampilGalat('Sesi kedaluwarsa', 'Muat ulang halaman ini lalu coba masuk kembali.');
        return;
      }
      const data = await res.json();
      if (data.login_status === 'success') {
        teks.textContent = 'Berhasil, mengalihkan…';
        window.location.href = data.redirect_url || '{{ url('/') }}';
        return;
      }
      sibuk(false);
      tampilGalat('Akun tidak terdaftar', 'Pastikan username dan password yang dimasukkan benar.');
      pass.select();
    } catch (err) {
      sibuk(false);
      tampilGalat('Tidak dapat terhubung', 'Periksa koneksi Anda lalu coba lagi.');
    }
  });
})();
</script>
</body>
</html>
