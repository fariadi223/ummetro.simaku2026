{{--
  Halaman mode maintenance (HTTP 503), dipakai saat `php artisan down`.
  Dirender oleh App\Exceptions\Handler (content.errors.503), atau dipra-render lewat
  `php artisan down --render="errors::503"` (resources/views/errors/503.blade.php meng-include file ini).
  Sengaja berdiri sendiri (tanpa layoutMaster/database) agar tetap tampil saat aplikasi sedang diperbaiki.
--}}
@php
  $appName    = config('variables.templateName', 'SIMAKU UM METRO');
  $retryAfter = isset($retryAfter) && is_numeric($retryAfter) ? (int) $retryAfter : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Sedang Pemeliharaan | {{ $appName }}</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Palet UM Metro: biru tua + kuning emas */
      --brand: #0b3c8d;
      --brand-dark: #062a63;
      --brand-mid: #1559b0;
      --brand-soft: #e8f0fc;
      --accent: #ffc728;
      --bg: linear-gradient(135deg, #1559b0 0%, #0b3c8d 48%, #062a63 100%);
      --card: rgba(255, 255, 255, .9);
      --text: #12203a;
      --muted: #5b6b86;
      --border: rgba(11, 60, 141, .14);
      --grid: rgba(255, 255, 255, .16);
    }
    @media (prefers-color-scheme: dark) {
      :root {
        --brand-soft: rgba(77, 139, 221, .22);
        --bg: linear-gradient(135deg, #0b3c8d 0%, #072350 55%, #041431 100%);
        --card: rgba(13, 30, 59, .82);
        --text: #e8eefb;
        --muted: #9fb0cc;
        --border: rgba(160, 200, 255, .18);
        --grid: rgba(255, 255, 255, .08);
      }
      .logo-wrap { background: #fff; }
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg);
      background-attachment: fixed;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      overflow-x: hidden;
      position: relative;
    }

    /* Latar belakang dekoratif */
    .blob {
      position: fixed;
      border-radius: 50%;
      filter: blur(70px);
      opacity: .45;
      z-index: 0;
      animation: float 14s ease-in-out infinite;
    }
    .blob.b1 { width: 420px; height: 420px; background: #ffc728; top: -140px; left: -120px; opacity: .38; }
    .blob.b2 { width: 360px; height: 360px; background: #ffc728; bottom: -140px; right: -100px; opacity: .26; animation-delay: -5s; }
    .blob.b3 { width: 260px; height: 260px; background: #5a9bea; top: 55%; left: 8%; opacity: .3; animation-delay: -9s; }
    .grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(var(--grid) 1px, transparent 1px),
        linear-gradient(90deg, var(--grid) 1px, transparent 1px);
      background-size: 44px 44px;
      mask-image: radial-gradient(ellipse at center, #000 20%, transparent 70%);
      -webkit-mask-image: radial-gradient(ellipse at center, #000 20%, transparent 70%);
    }
    @keyframes float {
      0%, 100% { transform: translate(0, 0) scale(1); }
      50% { transform: translate(30px, 25px) scale(1.08); }
    }

    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 620px;
      background: var(--card);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border: 1px solid var(--border);
      border-radius: 28px;
      padding: 44px 40px 36px;
      text-align: center;
      box-shadow: 0 30px 80px -30px rgba(4, 20, 49, .55);
      animation: rise .7s cubic-bezier(.2, .8, .2, 1) both;
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(24px); }
      to { opacity: 1; transform: none; }
    }

    .logo-wrap {
      display: inline-block;
      padding: 10px 16px;
      border-radius: 16px;
    }
    .logo { display: block; width: 92px; max-width: 100%; height: auto; }

    /* Ikon gear animasi */
    .gears {
      position: relative;
      width: 120px; height: 96px;
      margin: 28px auto 20px;
    }
    .gear { position: absolute; color: var(--brand); }
    .gear.g1 { width: 76px; height: 76px; left: 4px; top: 10px; animation: spin 6s linear infinite; }
    .gear.g2 { width: 46px; height: 46px; right: 4px; top: 0; color: var(--accent); animation: spin-rev 4s linear infinite; }
    .gear.g3 { width: 34px; height: 34px; right: 16px; bottom: 0; opacity: .7; animation: spin 3s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes spin-rev { to { transform: rotate(-360deg); } }

    .badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--brand-soft);
      color: var(--brand);
      font-size: 13px; font-weight: 700;
      letter-spacing: .04em; text-transform: uppercase;
      padding: 7px 14px; border-radius: 999px;
    }
    @media (prefers-color-scheme: dark) { .badge { color: #8fbaf5; } }
    .dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 0 0 rgba(255, 199, 40, .6);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(255, 199, 40, .6); }
      70% { box-shadow: 0 0 0 10px rgba(255, 199, 40, 0); }
      100% { box-shadow: 0 0 0 0 rgba(255, 199, 40, 0); }
    }

    h1 {
      font-size: clamp(26px, 5vw, 34px);
      font-weight: 800;
      line-height: 1.2;
      margin: 18px 0 12px;
      letter-spacing: -.02em;
    }
    h1 span { color: var(--brand); }
    @media (prefers-color-scheme: dark) { h1 span { color: #ffd666; } }
    p.lead { color: var(--muted); font-size: 16px; line-height: 1.65; max-width: 460px; margin: 0 auto; }

    .progress {
      height: 6px; border-radius: 999px;
      background: var(--brand-soft);
      overflow: hidden;
      margin: 28px auto 10px;
      max-width: 360px;
    }
    .progress > i {
      display: block; height: 100%; width: 40%;
      border-radius: inherit;
      background: linear-gradient(90deg, var(--brand), var(--brand-mid), var(--accent));
      animation: load 2.2s ease-in-out infinite;
    }
    @keyframes load {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(260%); }
    }
    .countdown { font-size: 13px; color: var(--muted); min-height: 18px; }
    .countdown b { color: var(--text); }

    .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px; border-radius: 14px;
      font: inherit; font-weight: 700; font-size: 15px;
      text-decoration: none; cursor: pointer;
      border: 1px solid transparent;
      transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .btn:hover { transform: translateY(-2px); }
    .btn-primary {
      background: var(--brand); color: #fff;
      box-shadow: 0 10px 24px -10px rgba(6, 42, 99, .7);
    }
    .btn-primary:hover { background: var(--brand-dark); }
    .btn-ghost { background: transparent; color: var(--text); border-color: var(--border); }
    .btn-ghost:hover { background: var(--brand-soft); }
    .btn svg { width: 18px; height: 18px; }

    footer {
      margin-top: 30px; padding-top: 20px;
      border-top: 1px dashed var(--border);
      font-size: 13px; color: var(--muted);
    }
    footer a { color: var(--brand); font-weight: 600; text-decoration: none; }
    @media (prefers-color-scheme: dark) { footer a { color: #8fbaf5; } }

    @media (max-width: 480px) {
      .card { padding: 32px 22px 28px; border-radius: 22px; }
      .logo { width: 76px; }
      .btn { flex: 1 1 100%; justify-content: center; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation: none !important; transition: none !important; }
    }
  </style>
</head>
<body>
  <div class="grid"></div>
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>

  <main class="card" role="main">
    <div class="logo-wrap">
      <img class="logo" src="{{ asset('assets/img/favicon/logo.png') }}" alt="Logo Universitas Muhammadiyah Metro">
    </div>

    <div class="gears" aria-hidden="true">
      @php
        $gear = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a7.07 7.07 0 0 0 .06-.94 7.07 7.07 0 0 0-.06-.94l2.03-1.58a.48.48 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a7.03 7.03 0 0 0-1.62-.94l-.36-2.54a.48.48 0 0 0-.48-.41h-3.84a.48.48 0 0 0-.48.41l-.36 2.54c-.59.24-1.13.56-1.62.94l-2.39-.96a.48.48 0 0 0-.59.22L2.73 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58a.48.48 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6A3.6 3.6 0 1 1 12 8.4a3.6 3.6 0 0 1 0 7.2z"/></svg>';
      @endphp
      <span class="gear g1">{!! $gear !!}</span>
      <span class="gear g2">{!! $gear !!}</span>
      <span class="gear g3">{!! $gear !!}</span>
    </div>

    <span class="badge"><span class="dot"></span> Pemeliharaan Sistem</span>

    <h1>Kami sedang <span>meningkatkan</span> layanan</h1>
    <p class="lead">
      SIMAKU — Sistem Informasi Manajemen dan Akuntansi Keuangan Universitas Muhammadiyah Metro
      sedang dalam pemeliharaan terjadwal. Mohon maaf atas ketidaknyamanannya, silakan kembali beberapa saat lagi.
      Data yang sudah tersimpan tetap aman.
    </p>

    <div class="progress" aria-hidden="true"><i></i></div>
    <div class="countdown" id="countdown">
      @if($retryAfter)
        Halaman akan dimuat ulang otomatis dalam <b id="sec">{{ $retryAfter }}</b> detik
      @endif
    </div>

    <div class="actions">
      <a href="{{ url()->current() }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
        Coba Lagi
      </a>
      <a href="https://ummetro.ac.id" class="btn btn-ghost" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
        ummetro.ac.id
      </a>
    </div>

    <footer>
      &copy; {{ date('Y') }} SIMAKU · PUSTIK
      <a href="https://ummetro.ac.id" target="_blank" rel="noopener">Universitas Muhammadiyah Metro</a>
    </footer>
  </main>

  @if($retryAfter)
  <script>
    (function () {
      var sec = {{ $retryAfter }};
      var el = document.getElementById('sec');
      var timer = setInterval(function () {
        sec--;
        if (el) el.textContent = Math.max(sec, 0);
        if (sec <= 0) { clearInterval(timer); window.location.reload(); }
      }, 1000);
    })();
  </script>
  @endif
</body>
</html>
