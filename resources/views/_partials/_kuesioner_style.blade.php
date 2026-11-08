{{-- Gaya tampilan kuesioner, dipakai bersama oleh pratinjau administrator
     dan halaman pengisian alumni supaya keduanya tampil sama. --}}
<style>
  /* Pratinjau dibuat mirip tampilan alumni: satu kolom, mobile-first. */
  .kp-bar {
    position: sticky; top: 0; z-index: 10;
    background: #fff3cd; border-bottom: 1px solid #ffe69c; color: #664d03;
    padding: .6rem 1rem; font-size: .85rem;
  }
  html.dark-style .kp-bar { background: #3a3222; border-bottom-color: #514529; color: #ffda6a; }

  /* Lebar penuh (col-12): isi kuesioner memakai semua ruang konten. */
  .kp-wrap { width: 100%; max-width: 100%; margin: 0; }

  .kp-bagian { margin-bottom: 1.5rem; }
  .kp-bagian-head { padding-bottom: .6rem; border-bottom: 2px solid var(--bs-primary); margin-bottom: 1rem; }
  .kp-bagian-head h5 { margin: 0; font-weight: 700; }

  .kp-soal { padding: 1rem 0; border-bottom: 1px solid var(--bs-border-color); }
  .kp-soal:last-child { border-bottom: 0; }
  .kp-soal.kp-tersembunyi { display: none; }

  .kp-teks { font-weight: 500; margin-bottom: .6rem; }
  .kp-kode { font-family: var(--bs-font-monospace); font-size: .72rem; }

  /* Target sentuh lega + 16px agar iOS tidak auto-zoom */
  .kp-soal .form-control,
  .kp-soal .form-select { font-size: 16px; min-height: 46px; }

  .kp-pilihan { display: grid; gap: .1rem; }
  .kp-pilihan .form-check {
    min-height: 44px; display: flex; align-items: center;
    padding-left: 2rem; margin-bottom: 0;
  }
  .kp-pilihan .form-check-input { width: 1.15rem; height: 1.15rem; margin-left: -2rem; }
  .kp-pilihan .form-check-label { padding-left: .4rem; font-size: .92rem; }

  /* Skala: angka berderet, tetap bisa digeser di layar kecil */
  .kp-skala { display: flex; gap: .4rem; flex-wrap: wrap; }
  .kp-skala .form-check {
    display: flex; margin: 0; padding: 0; text-align: center;
    min-width: 52px; min-height: 52px;
    border: 1px solid var(--bs-border-color); border-radius: .5rem;
  }
  .kp-skala .form-check-input { display: none; }
  /* Label bisa berupa angka atau kata ("Sangat Rendah"), jadi kotaknya
     memakai padding dan tinggi minimum supaya teks panjang tetap rapi. */
  .kp-skala .form-check-label {
    display: grid; place-items: center; flex: 1; min-height: 50px;
    padding: .4rem .65rem; line-height: 1.25;
    cursor: pointer; font-weight: 600;
  }
  .kp-skala .form-check-input:checked + .form-check-label {
    background: var(--bs-primary); color: #fff; border-radius: .45rem;
  }

  /* ---- Matriks kompetensi A/B (kode f17xx) ---- */

  .kp-mx-tabel { display: grid; gap: .35rem; }

  /* Kepala tabel hanya berguna saat kolomnya memang berdampingan. */
  .kp-mx-head { display: none; }

  .kp-mx-row {
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem; padding: .7rem .8rem;
  }

  .kp-mx-label { font-weight: 600; font-size: .92rem; margin-bottom: .5rem; }

  /* Di layar kecil tiap sisi diberi label sendiri, karena tidak ada kepala tabel. */
  .kp-mx-cell + .kp-mx-cell { margin-top: .5rem; }
  .kp-mx-cell::before {
    content: attr(data-sisi);
    display: block; margin-bottom: .25rem;
    font-size: .74rem; color: var(--bs-secondary);
  }

  .kp-mx-kosong { font-size: .78rem; font-style: italic; }

  /* Skala versi ringkas supaya dua kolom cukup dalam satu baris. */
  .kp-skala-sm .form-check { min-width: 42px; min-height: 42px; }
  .kp-skala-sm .form-check-label {
    min-height: 40px; padding: .3rem .45rem; font-size: .88rem;
  }

  @media (min-width: 992px) {
    .kp-mx-head,
    .kp-mx-row {
      display: grid;
      grid-template-columns: 15rem 1fr 1fr;
      gap: 1rem; align-items: center;
    }

    .kp-mx-head {
      padding: 0 .8rem .4rem;
      font-size: .76rem; color: var(--bs-secondary);
      text-transform: uppercase; letter-spacing: .02em;
    }

    .kp-mx-label { margin-bottom: 0; }

    /* Label per sisi tidak perlu lagi: sudah ada di kepala tabel. */
    .kp-mx-cell + .kp-mx-cell { margin-top: 0; }
    .kp-mx-cell::before { display: none; }
  }

  .kp-cabang {
    font-size: .74rem; color: var(--bs-secondary);
    display: inline-flex; align-items: center; gap: .25rem;
  }

  /* ---- Penanda pertanyaan yang gagal validasi (halaman pengisian) ---- */

  .kp-soal.kp-salah {
    border-left: 3px solid var(--bs-danger);
    padding-left: .8rem;
    background: rgba(var(--bs-danger-rgb), .04);
    border-radius: .35rem;
  }
  html.dark-style .kp-soal.kp-salah { background: rgba(var(--bs-danger-rgb), .1); }

  .kp-pesan-salah {
    margin-top: .4rem;
    font-size: .82rem;
    color: var(--bs-danger);
  }
</style>
