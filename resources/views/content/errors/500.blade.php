@php
  $appName = config('variables.templateName', 'SIMAKU UM METRO');
  $kodeInsiden = $kodeInsiden ?? null;
@endphp
{{--
  Halaman error 500.

  Sengaja tidak memakai layouts/layoutMaster: error 500 sering justru berasal
  dari lapisan yang dipakai layout itu (database, menu, helper). Kalau halaman
  ini ikut memuatnya, render-nya bisa gagal lagi dan pengguna hanya melihat
  halaman putih. Jadi seluruh gaya ditulis inline di sini.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Terjadi Kesalahan | {{ $appName }}</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Palet UM Metro, sama dengan halaman 503 */
      --blue-900: #062a63;
      --blue-700: #0b3c8d;
      --blue-500: #1559b0;
      --yellow: #ffc728;
      --card: rgba(255, 255, 255, .9);
      --text: #12203a;
      --muted: #5b6b86;
      --border: rgba(11, 60, 141, .14);
      --grid: rgba(255, 255, 255, .16);
      --accent-soft: rgba(255, 199, 40, .22);
    }
    @media (prefers-color-scheme: dark) {
      :root {
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
      background: linear-gradient(135deg, var(--blue-500) 0%, var(--blue-700) 48%, var(--blue-900) 100%);
      background-attachment: fixed;
      color: var(--text);
      display: flex; align-items: center; justify-content: center;
      padding: 24px 16px;
      overflow-x: hidden;
      position: relative;
    }

    .blob {
      position: fixed; border-radius: 50%;
      filter: blur(70px); z-index: 0;
      animation: float 14s ease-in-out infinite;
    }
    .blob.b1 { width: 420px; height: 420px; background: #ffc728; top: -140px; left: -120px; opacity: .3; }
    .blob.b2 { width: 340px; height: 340px; background: #5a9bea; bottom: -130px; right: -90px; opacity: .3; animation-delay: -6s; }
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
      50% { transform: translate(28px, 22px) scale(1.07); }
    }

    .card {
      position: relative; z-index: 1;
      width: 100%; max-width: 620px;
      background: var(--card);
      backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
      border: 1px solid var(--border);
      border-radius: 28px;
      padding: 44px 40px 36px;
      text-align: center;
      box-shadow: 0 30px 80px -30px rgba(4, 20, 49, .55);
      overflow: hidden;
      animation: rise .7s cubic-bezier(.2, .8, .2, 1) both;
    }
    .card::before {
      content: ''; position: absolute; inset: 0 0 auto; height: 6px;
      background: linear-gradient(90deg, var(--blue-700) 0%, var(--blue-500) 45%, var(--yellow) 100%);
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(24px); }
      to { opacity: 1; transform: none; }
    }

    .logo-wrap { display: inline-block; padding: 10px 14px; border-radius: 16px; }
    .logo { display: block; width: 76px; height: auto; }

    .kode {
      font-size: clamp(64px, 16vw, 104px);
      font-weight: 800; line-height: 1;
      letter-spacing: -.04em;
      margin: 18px 0 2px;
      color: var(--blue-700);
      -webkit-text-stroke: 0;
    }
    @media (prefers-color-scheme: dark) { .kode { color: #ffd666; } }

    .badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--accent-soft);
      border: 1px solid var(--yellow);
      color: var(--text);
      font-size: 13px; font-weight: 700;
      letter-spacing: .04em; text-transform: uppercase;
      padding: 7px 14px; border-radius: 999px;
    }
    .badge svg { width: 16px; height: 16px; color: #e0a900; }

    h1 {
      font-size: clamp(22px, 4.5vw, 30px);
      font-weight: 800; line-height: 1.25;
      margin: 16px 0 12px;
      letter-spacing: -.02em;
    }
    p.lead { color: var(--muted); font-size: 16px; line-height: 1.65; max-width: 460px; margin: 0 auto; }

    .insiden {
      margin: 24px auto 0; max-width: 460px;
      border: 1px solid var(--border);
      border-left: 4px solid var(--yellow);
      border-radius: 12px;
      padding: 14px 16px;
      text-align: left;
      font-size: 13px; color: var(--muted);
    }
    .insiden strong { display: block; margin-bottom: 4px; color: var(--text); }
    .insiden code {
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
      font-size: 15px; font-weight: 700;
      color: var(--text);
      letter-spacing: .08em;
      user-select: all;
    }

    .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px; border-radius: 14px;
      font: inherit; font-weight: 700; font-size: 15px;
      text-decoration: none; cursor: pointer;
      border: 1px solid transparent;
      transition: transform .15s ease, background .15s ease;
    }
    .btn:hover { transform: translateY(-2px); }
    .btn-primary {
      background: var(--blue-700); color: #fff;
      box-shadow: 0 10px 24px -10px rgba(6, 42, 99, .7);
    }
    .btn-primary:hover { background: var(--blue-900); }
    .btn-ghost { background: var(--accent-soft); color: var(--text); border-color: var(--yellow); }
    .btn-ghost:hover { background: var(--yellow); color: #12203a; }
    .btn svg { width: 18px; height: 18px; }

    footer {
      margin-top: 30px; padding-top: 20px;
      border-top: 1px dashed var(--border);
      font-size: 13px; color: var(--muted);
    }
    footer a { color: var(--blue-700); font-weight: 600; text-decoration: none; }
    @media (prefers-color-scheme: dark) { footer a { color: #8fbaf5; } }

    @media (max-width: 480px) {
      .card { padding: 32px 22px 28px; border-radius: 22px; }
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

  <main class="card" role="main">
    <div class="logo-wrap">
      <img class="logo" src="{{ asset('assets/img/favicon/logo.png') }}" alt="Logo Universitas Muhammadiyah Metro">
    </div>

    <div class="kode">500</div>

    <span class="badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <path d="M12 9v4"/><path d="M12 17h.01"/>
      </svg>
      Kesalahan Server
    </span>

    <h1>Terjadi kesalahan di sisi server</h1>
    <p class="lead">
      Permintaan Anda tidak dapat diselesaikan karena ada gangguan pada sistem,
      bukan karena kesalahan Anda. Kejadian ini sudah dicatat dan akan ditindaklanjuti.
      Silakan coba lagi beberapa saat lagi.
    </p>

    @if($kodeInsiden)
    <div class="insiden">
      <strong>Kode insiden</strong>
      Sebutkan kode <code>{{ $kodeInsiden }}</code> saat menghubungi pengelola,
      supaya penyebabnya bisa langsung ditemukan di catatan sistem.
    </div>
    @endif

    <div class="actions">
      <a href="{{ url()->current() }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
        Coba Lagi
      </a>
      <a href="{{ url('/') }}" class="btn btn-ghost">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/></svg>
        Ke Halaman Awal
      </a>
    </div>

    <footer>
      Butuh bantuan? Hubungi <a href="mailto:pustik@ummetro.ac.id">pustik@ummetro.ac.id</a><br>
      &copy; {{ date('Y') }} SIMAKU · PUSTIK
      <a href="https://ummetro.ac.id" target="_blank" rel="noopener">Universitas Muhammadiyah Metro</a>
    </footer>
  </main>
</body>
</html>
