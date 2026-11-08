<?php

namespace App\Helpers;


use Config;
use Illuminate\Support\Str;

class Helpers
{
  public static function appClasses()
  {

    $data = config('custom.custom');


    // default data array
    $DefaultData = [
      'myLayout' => 'vertical',
      'myTheme' => 'theme-default',
      'myStyle' => 'light',
      'myRTLSupport' => true,
      'myRTLMode' => true,
      'hasCustomizer' => true,
      'showDropdownOnHover' => true,
      'displayCustomizer' => true,
      'menuFixed' => true,
      'menuCollapsed' => false,
      'navbarFixed' => true,
      'footerFixed' => false,
      'menuFlipped' => false,
      // 'menuOffcanvas' => false,
      'customizerControls' => [
        'rtl',
        'style',
        'layoutType',
        'showDropdownOnHover',
        'layoutNavbarFixed',
        'layoutFooterFixed',
        'themes',
      ],
      //   'defaultLanguage'=>'en',
    ];

    // if any key missing of array from custom.php file it will be merge and set a default value from dataDefault array and store in data variable
    $data = array_merge($DefaultData, $data);

    // All options available in the template
    $allOptions = [
      'myLayout' => ['vertical', 'horizontal', 'blank'],
      'menuCollapsed' => [true, false],
      'hasCustomizer' => [true, false],
      'showDropdownOnHover' => [true, false],
      'displayCustomizer' => [true, false],
      'myStyle' => ['light', 'dark'],
      'myTheme' => ['theme-default', 'theme-bordered', 'theme-semi-dark'],
      'myRTLSupport' => [true, false],
      'myRTLMode' => [true, false],
      'menuFixed' => [true, false],
      'navbarFixed' => [true, false],
      'footerFixed' => [true, false],
      'menuFlipped' => [true, false],
      // 'menuOffcanvas' => [true, false],
      'customizerControls' => [],
      // 'defaultLanguage'=>array('en'=>'en','fr'=>'fr','de'=>'de','pt'=>'pt'),
    ];

    //if myLayout value empty or not match with default options in custom.php config file then set a default value
    foreach ($allOptions as $key => $value) {
      if (array_key_exists($key, $DefaultData)) {
        if (gettype($DefaultData[$key]) === gettype($data[$key])) {
          // data key should be string
          if (is_string($data[$key])) {
            // data key should not be empty
            if (isset($data[$key]) && $data[$key] !== null) {
              // data key should not be exist inside allOptions array's sub array
              if (!array_key_exists($data[$key], $value)) {
                // ensure that passed value should be match with any of allOptions array value
                $result = array_search($data[$key], $value, 'strict');
                if (empty($result) && $result !== 0) {
                  $data[$key] = $DefaultData[$key];
                }
              }
            } else {
              // if data key not set or
              $data[$key] = $DefaultData[$key];
            }
          }
        } else {
          $data[$key] = $DefaultData[$key];
        }
      }
    }
    //layout classes
    $layoutClasses = [
      'layout' => $data['myLayout'],
      'theme' => $data['myTheme'],
      'style' => $data['myStyle'],
      'rtlSupport' => $data['myRTLSupport'],
      'rtlMode' => $data['myRTLMode'],
      'textDirection' => $data['myRTLMode'],
      'menuCollapsed' => $data['menuCollapsed'],
      'hasCustomizer' => $data['hasCustomizer'],
      'showDropdownOnHover' => $data['showDropdownOnHover'],
      'displayCustomizer' => $data['displayCustomizer'],
      'menuName' => $data['menuName'],
      'menuFixed' => $data['menuFixed'],
      'navbarFixed' => $data['navbarFixed'],
      'footerFixed' => $data['footerFixed'],
      'menuFlipped' => $data['menuFlipped'],
      // 'menuOffcanvas' => $data['menuOffcanvas'],
      'customizerControls' => $data['customizerControls'],
    ];

    // sidebar Collapsed
    if ($layoutClasses['menuCollapsed'] == true) {
      $layoutClasses['menuCollapsed'] = 'layout-menu-collapsed';
    }

    // Menu Fixed
    if ($layoutClasses['menuFixed'] == true) {
      $layoutClasses['menuFixed'] = 'layout-menu-fixed';
    }

    // Navbar Fixed
    if ($layoutClasses['navbarFixed'] == true) {
      $layoutClasses['navbarFixed'] = 'layout-navbar-fixed';
    }

    // Footer Fixed
    if ($layoutClasses['footerFixed'] == true) {
      $layoutClasses['footerFixed'] = 'layout-footer-fixed';
    }

    // Menu Flipped
    if ($layoutClasses['menuFlipped'] == true) {
      $layoutClasses['menuFlipped'] = 'layout-menu-flipped';
    }

    // Menu Offcanvas
    // if ($layoutClasses['menuOffcanvas'] == true) {
    //   $layoutClasses['menuOffcanvas'] = 'layout-menu-offcanvas';
    // }

    // RTL Supported template
    if ($layoutClasses['rtlSupport'] == true) {
      $layoutClasses['rtlSupport'] = '/rtl';
    }

    // RTL Layout/Mode
    if ($layoutClasses['rtlMode'] == true) {
      $layoutClasses['rtlMode'] = 'rtl';
      $layoutClasses['textDirection'] = 'rtl';
    } else {
      $layoutClasses['rtlMode'] = 'ltr';
      $layoutClasses['textDirection'] = 'ltr';
    }

    // Show DropdownOnHover for Horizontal Menu
    if ($layoutClasses['showDropdownOnHover'] == true) {
      $layoutClasses['showDropdownOnHover'] = 'true';
    } else {
      $layoutClasses['showDropdownOnHover'] = 'false';
    }

    // To hide/show display customizer UI, not js
    if ($layoutClasses['displayCustomizer'] == true) {
      $layoutClasses['displayCustomizer'] = 'true';
    } else {
      $layoutClasses['displayCustomizer'] = 'false';
    }

    return $layoutClasses;
  }

  /**
   * Administrator asli saat mode "login as" sedang berjalan, atau null bila
   * tidak. Id-nya hanya ada di session sisi server, jadi akun alumni yang
   * sedang diperankan tidak bisa memalsukannya.
   */
  /**
   * Nama MAK/bidang yang tersimpan kapital ("AIK, KERJASAMA DAN ...") menjadi
   * "AIK, Kerjasama dan ...": singkatan (<= 3 huruf) tetap kapital.
   */
  public static function judulMak(?string $teks): string
  {
    return preg_replace_callback('/[A-Za-z]+/', function ($m) {
      $k = $m[0];
      if (strtolower($k) === 'dan') {
        return 'dan';
      }

      return strlen($k) <= 3 ? strtoupper($k) : ucfirst(strtolower($k));
    }, trim((string) $teks));
  }

  /**
   * Nominal dalam kata, padanan rupiah()/penyebut() terbilang_helper v1:
   * 1250000 -> "Satu Juta Dua Ratus Lima Puluh Ribu rupiah". Pecahan diabaikan.
   */
  public static function terbilang(float|int $nilai): string
  {
    $n = (int) round(abs($nilai));
    $kata = $n === 0 ? 'Nol' : trim(self::penyebut($n));

    return ($nilai < 0 ? 'minus ' : '') . $kata . ' rupiah';
  }

  private static function penyebut(int $n): string
  {
    $huruf = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

    return match (true) {
      $n === 0 => '',
      $n < 12 => ' ' . $huruf[$n],
      $n < 20 => self::penyebut($n - 10) . ' Belas',
      $n < 100 => self::penyebut(intdiv($n, 10)) . ' Puluh' . self::penyebut($n % 10),
      $n < 200 => ' Seratus' . self::penyebut($n - 100),
      $n < 1000 => self::penyebut(intdiv($n, 100)) . ' Ratus' . self::penyebut($n % 100),
      $n < 2000 => ' Seribu' . self::penyebut($n - 1000),
      $n < 1_000_000 => self::penyebut(intdiv($n, 1000)) . ' Ribu' . self::penyebut($n % 1000),
      $n < 1_000_000_000 => self::penyebut(intdiv($n, 1_000_000)) . ' Juta' . self::penyebut($n % 1_000_000),
      $n < 1_000_000_000_000 => self::penyebut(intdiv($n, 1_000_000_000)) . ' Milyar' . self::penyebut($n % 1_000_000_000),
      default => self::penyebut(intdiv($n, 1_000_000_000_000)) . ' Trilyun' . self::penyebut($n % 1_000_000_000_000),
    };
  }

  public static function impersonasiAdmin()
  {
    // Fitur "login as" belum ada di SIMAKU v2.
    return null;
  }

  public static function updatePageConfig($pageConfigs)
  {
    $demo = 'custom';
    if (isset($pageConfigs)) {
      if (count($pageConfigs) > 0) {
        foreach ($pageConfigs as $config => $val) {
          Config::set('custom.' . $demo . '.' . $config, $val);
        }
      }
    }
  }
}
