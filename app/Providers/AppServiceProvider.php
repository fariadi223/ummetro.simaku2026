<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->bind('path.public', function() {
      return base_path().'/../public_html';
    });
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {


    //
    config(['app.locale' => 'id']);
    
    Carbon::setLocale('id');
    
    date_default_timezone_set('Asia/Jakarta');

    Str::macro('currency', function ($price)
    {
      return number_format($price, 2, '.', ',');
    });
  }
}
