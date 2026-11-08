<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Kode singkat yang mewakili satu kejadian error, dibuat saat error dicatat.
     * Ditempelkan ke log dan ditampilkan di halaman 500 supaya laporan pengguna
     * bisa langsung dicocokkan dengan barisnya di storage/logs.
     */
    private ?string $kodeInsiden = null;

    /**
     * Exception yang memiliki kodeInsiden di atas, dan exception yang sedang
     * dirender. Keduanya dibandingkan sebelum kode ditampilkan: abort(500)
     * tidak pernah dicatat ke log, jadi kodenya tidak boleh ikut muncul --
     * tanpa penjagaan ini ia akan memakai kode milik error sebelumnya.
     */
    private ?Throwable $insidenMilik = null;

    private ?Throwable $sedangDirender = null;

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Buat kode insiden sebelum error dicatat, lalu selipkan ke konteks log.
     * Dipasang lewat withContext, bukan baris log tersendiri, supaya kodenya
     * menempel pada entri error yang sudah ada -- tidak ada log ganda.
     */
    public function report(Throwable $e): void
    {
        if (! $this->shouldntReport($e)) {
            $this->kodeInsiden = Str::upper(Str::random(8));
            $this->insidenMilik = $e;

            Log::withContext(['insiden' => $this->kodeInsiden]);
        }

        parent::report($e);
    }

    /**
     * Halaman error 500 milik aplikasi.
     *
     * Dipasang di renderHttpException, bukan di render(), supaya penanganan
     * khusus milik Laravel tetap utuh: ValidationException tetap kembali ke
     * form dengan pesannya, AuthenticationException tetap dialihkan ke login,
     * dan status lain (403, 419, 429) tidak ikut tertangkap sebagai 500.
     *
     * Catatan: saat APP_DEBUG=true, Laravel menampilkan halaman debug dan
     * method ini tidak pernah dipanggil untuk exception biasa.
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        if ($e->getStatusCode() === 500) {
            $kode = $this->sedangDirender === $this->insidenMilik ? $this->kodeInsiden : null;

            return response()->view(
                'content.errors.500',
                ['kodeInsiden' => $kode],
                500,
                $e->getHeaders()
            );
        }

        return parent::renderHttpException($e);
    }

    function render($request, Throwable $exception)
    {
        $this->sedangDirender = $exception;

        if ($this->isHttpException($exception)) {
            $pageConfigs = ['myLayout' => 'blank'];
            if ($exception->getStatusCode() == 404) {
                return response()->view('content.errors.404', ['pageConfigs' => $pageConfigs], 404);
            }
            if ($exception->getStatusCode() == 401) {
                return response()->view('content.errors.401', ['pageConfigs' => $pageConfigs], 401);
            }
            // Mode maintenance (php artisan down)
            if ($exception->getStatusCode() == 503 && !$request->expectsJson()) {
                $headers = $exception->getHeaders();
                return response()->view('content.errors.503', [
                    'retryAfter' => $headers['Retry-After'] ?? null,
                ], 503, $headers);
            }
        }
        return parent::render($request, $exception);
    }
}
