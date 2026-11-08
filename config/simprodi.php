<?php

/*
|--------------------------------------------------------------------------
| Koneksi ke SIMPRODI
|--------------------------------------------------------------------------
|
| Dipakai App\Services\SimprodiAlumniService untuk menarik data lulusan.
|
| Nilai bawaan di sini menyalin perilaku library Apiclient milik proyek
| CodeIgniter lama: kunci rahasia dikirim sebagai sha1(), badan permintaan
| berupa JSON mentah, dan content type dibiarkan seperti default cURL
| (application/x-www-form-urlencoded) -- bukan application/json. Kalau
| SIMPRODI ternyata mengharap JSON, ubah SIMPRODI_CONTENT_TYPE di .env.
|
*/

return [
  'url' => env('SIMPRODI_URL', 'https://simprodi.ummetro.ac.id/api/'),
  'secret' => env('SIMPRODI_SECRET'),
  'content_type' => env('SIMPRODI_CONTENT_TYPE', 'application/x-www-form-urlencoded'),
  'timeout' => (int) env('SIMPRODI_TIMEOUT', 120),

  // SIMPRODI masih dilayani lewat http://, jadi verifikasi sertifikat tidak
  // relevan. Disediakan agar mudah diaktifkan bila nanti pindah ke https.
  'verify' => (bool) env('SIMPRODI_VERIFY_SSL', false),
];
