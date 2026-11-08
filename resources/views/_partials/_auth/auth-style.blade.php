{{-- Gaya halaman autentikasi. Ditulis mobile-first: aturan dasar untuk layar kecil,
     breakpoint lg ke atas baru menambahkan panel brand dua kolom. --}}
<style>
  :root {
    --au-navy: #0b2a4a;
    --au-navy-2: #123c68;
    --au-teal: #0f9d8f;
    --au-teal-soft: #5fe3d3;
    --au-ink: #2b3445;
    --au-muted: #7a8699;
    --au-line: #e3e7ef;
    --au-surface: #ffffff;
    --au-bg: #f4f6fb;
    --au-tap: 48px;
  }

  html,
  body {
    overflow-x: hidden;
  }

  /* ---------------- Dasar: tampilan mobile ---------------- */
  .au-wrap {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--au-bg);
  }

  /* Header ringkas khusus mobile */
  .au-mhead {
    padding: calc(1.25rem + env(safe-area-inset-top)) 1rem 2.6rem;
    background: linear-gradient(145deg, var(--au-navy) 0%, var(--au-navy-2) 100%);
    color: #fff;
    text-align: center;
    position: relative;
  }

  .au-mhead::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 1.6rem;
    background: var(--au-bg);
    border-radius: 1.6rem 1.6rem 0 0;
  }

  .au-mark {
    width: 54px;
    height: 54px;
    margin: 0 auto .7rem;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, .15);
    border: 1px solid rgba(255, 255, 255, .25);
  }

  .au-mark i {
    font-size: 1.7rem;
  }

  .au-mark img {
    width: 72%;
    height: 72%;
    object-fit: contain;
    display: block;
  }

  .au-mhead h1 {
    color: #fff;
    font-size: 1.12rem;
    font-weight: 700;
    letter-spacing: .02em;
    margin: 0 0 .2rem;
  }

  .au-mhead p {
    margin: 0;
    font-size: .82rem;
    color: rgba(255, 255, 255, .78);
  }

  /* Panel brand hanya tampil di layar besar */
  .au-brand {
    display: none;
  }

  /* Area form */
  .au-form-side {
    flex: 1 1 auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 0 1rem calc(1.5rem + env(safe-area-inset-bottom));
    margin-top: -1.4rem;
    position: relative;
    z-index: 1;
  }

  .au-card {
    width: 100%;
    max-width: 27rem;
    background: var(--au-surface);
    border-radius: 18px;
    padding: 1.5rem 1.25rem;
    box-shadow: 0 10px 30px rgba(11, 42, 74, .10);
  }

  .au-head h2 {
    font-size: 1.12rem;
    font-weight: 700;
    color: var(--au-ink);
    margin-bottom: .25rem;
  }

  .au-head p {
    color: var(--au-muted);
    font-size: .85rem;
    margin-bottom: 1.35rem;
  }

  .au-card .form-label {
    font-weight: 600;
    font-size: .84rem;
    color: var(--au-ink);
    margin-bottom: .35rem;
  }

  /* 16px mencegah Safari iOS auto-zoom saat field difokus */
  .au-card .form-control {
    font-size: 16px;
    min-height: var(--au-tap);
    padding-top: .6rem;
    padding-bottom: .6rem;
  }

  .au-card .input-group-text {
    background: #f6f8fc;
    color: var(--au-muted);
    min-width: var(--au-tap);
    justify-content: center;
  }

  /* Tombol mata password: target sentuh penuh */
  .au-card .form-password-toggle .input-group-text.cursor-pointer {
    min-width: var(--au-tap);
    min-height: var(--au-tap);
  }

  .au-btn {
    background: var(--au-navy);
    border-color: var(--au-navy);
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    min-height: var(--au-tap);
    border-radius: 10px;
  }

  .au-btn:hover,
  .au-btn:focus,
  .au-btn:active {
    background: var(--au-navy-2);
    border-color: var(--au-navy-2);
    color: #fff;
  }

  .au-btn:disabled {
    background: var(--au-navy);
    border-color: var(--au-navy);
    opacity: .65;
  }

  /* Checkbox & link: perbesar area sentuh */
  .au-card .form-check {
    min-height: var(--au-tap);
    display: flex;
    align-items: center;
    padding-left: 1.9rem;
  }

  .au-card .form-check-input {
    width: 1.15rem;
    height: 1.15rem;
    margin-left: -1.9rem;
  }

  .au-card .form-check-label {
    font-size: .88rem;
    padding-left: .15rem;
  }

  .au-alert {
    font-size: .85rem;
    border-radius: 10px;
  }

  .au-note {
    margin-top: 1.25rem;
    padding: .75rem .85rem;
    border-radius: 10px;
    background: #eef6ff;
    border: 1px solid #d5e7fb;
    color: #27496b;
    font-size: .8rem;
    line-height: 1.5;
  }

  .au-help {
    margin: 1.1rem 0 0;
    text-align: center;
    font-size: .82rem;
    color: var(--au-muted);
  }

  .au-copy {
    margin: 1.4rem 0 0;
    text-align: center;
    font-size: .74rem;
    color: var(--au-muted);
  }

  .au-copy a {
    color: var(--au-navy);
    font-weight: 600;
  }

  /* ---------------- Tablet ---------------- */
  @media (min-width: 576px) {
    .au-card {
      padding: 1.85rem 1.6rem;
      border: 1px solid var(--au-line);
    }

    .au-mhead {
      padding-top: 2rem;
    }
  }

  /* ---------------- Desktop: dua kolom ---------------- */
  @media (min-width: 992px) {
    .au-wrap {
      flex-direction: row;
    }

    .au-mhead {
      display: none;
    }

    .au-brand {
      display: flex;
      position: relative;
      flex: 1 1 55%;
      padding: 3rem 3.5rem;
      color: #fff;
      background: linear-gradient(145deg, var(--au-navy) 0%, var(--au-navy-2) 55%, #17507f 100%);
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }

    .au-brand::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(circle at 18% 22%, rgba(15, 157, 143, .35) 0, transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(255, 255, 255, .10) 0, transparent 40%);
      pointer-events: none;
    }

    .au-brand-top {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      gap: .9rem;
      text-align: left;
    }

    .au-brand-top .au-mark {
      margin: 0;
      flex: 0 0 52px;
      width: 52px;
      height: 52px;
    }

    .au-brand-top h6 {
      margin: 0;
      color: #fff;
      font-weight: 700;
      letter-spacing: .04em;
      font-size: .9rem;
    }

    .au-brand-top span {
      font-size: .76rem;
      color: rgba(255, 255, 255, .72);
    }

    .au-brand-body {
      position: relative;
      z-index: 2;
      max-width: 30rem;
    }

    .au-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .34rem .8rem;
      border-radius: 999px;
      font-size: .74rem;
      font-weight: 600;
      background: rgba(15, 157, 143, .22);
      border: 1px solid rgba(15, 157, 143, .45);
      color: #a7f3ea;
      margin-bottom: 1.3rem;
    }

    .au-brand-body h2 {
      color: #fff;
      font-size: 2.05rem;
      line-height: 1.25;
      font-weight: 700;
      margin-bottom: .9rem;
    }

    .au-brand-body h2 em {
      font-style: normal;
      color: var(--au-teal-soft);
    }

    .au-brand-body > p {
      color: rgba(255, 255, 255, .80);
      font-size: .95rem;
      margin-bottom: 1.8rem;
    }

    .au-points {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: .85rem;
    }

    .au-points li {
      display: flex;
      align-items: center;
      gap: .75rem;
      font-size: .9rem;
      color: rgba(255, 255, 255, .92);
    }

    .au-points li i {
      width: 32px;
      height: 32px;
      flex: 0 0 32px;
      border-radius: 9px;
      display: grid;
      place-items: center;
      background: rgba(255, 255, 255, .13);
      font-size: 1rem;
    }

    .au-brand-foot {
      position: relative;
      z-index: 2;
      font-size: .78rem;
      color: rgba(255, 255, 255, .6);
    }

    .au-form-side {
      flex: 1 1 45%;
      align-items: center;
      margin-top: 0;
      padding: 2.5rem 1.5rem;
    }

    .au-card {
      padding: 2.25rem 2rem;
      box-shadow: 0 18px 44px rgba(11, 42, 74, .10);
    }

    .au-head h2 {
      font-size: 1.3rem;
    }
  }

  /* ---------------- Dark mode template ---------------- */
  html.dark-style .au-wrap { background: #232333; }
  html.dark-style .au-mhead::after { background: #232333; }
  html.dark-style .au-card {
    background: #2b2c40;
    border-color: #434663;
    box-shadow: 0 12px 34px rgba(0, 0, 0, .35);
  }
  html.dark-style .au-head h2,
  html.dark-style .au-card .form-label { color: #dfdfe9; }
  html.dark-style .au-head p,
  html.dark-style .au-help,
  html.dark-style .au-copy { color: #a0a3bd; }
  html.dark-style .au-card .input-group-text { background: #35364e; color: #a0a3bd; }
  html.dark-style .au-note {
    background: #2f3b4d;
    border-color: #3f5168;
    color: #cfe0f2;
  }
  html.dark-style .au-copy a { color: var(--au-teal-soft); }

  /* ---------------- Hormati preferensi gerak ---------------- */
  @media (prefers-reduced-motion: reduce) {
    * {
      animation-duration: .01ms !important;
      transition-duration: .01ms !important;
    }
  }
</style>
