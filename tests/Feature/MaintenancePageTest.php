<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Halaman mode maintenance (503). Tidak menjalankan `artisan down` (agar aplikasi yang berjalan tidak ikut mati):
 * memicu HttpException 503 pada route sementara, dan merender view errors::503 langsung.
 * Jalankan: php artisan test --filter=MaintenancePageTest
 */
class MaintenancePageTest extends TestCase
{
    public function test_halaman_503_simaku(): void
    {
        Route::get('/_uji-maintenance', fn () => abort(503, '', ['Retry-After' => 60]));

        $this->get('/_uji-maintenance')->assertStatus(503)
            ->assertSee('Sedang Pemeliharaan | SIMAKU UM METRO', false)
            ->assertSee('Sistem Informasi Manajemen dan Akuntansi Keuangan')
            ->assertSee('dalam <b id="sec">60</b> detik', false)
            ->assertDontSee('Tracer');
    }

    public function test_json_tetap_json(): void
    {
        Route::get('/_uji-maintenance-json', fn () => abort(503));
        $this->getJson('/_uji-maintenance-json')->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_view_prarender(): void
    {
        (new \Illuminate\Foundation\Exceptions\RegisterErrorViewPaths)(); // seperti yang dilakukan artisan down --render
        $html = view('errors::503', ['retryAfter' => null])->render();
        $this->assertStringContainsString('Pemeliharaan Sistem', $html);
        $this->assertStringNotContainsString('id="sec"', $html);
    }
}
