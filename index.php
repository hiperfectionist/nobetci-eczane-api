<?php

/**
 * Türkiye Nöbetçi Eczane API
 * Her il için bağımsız, modüler scraper mimarisi.
 */

declare(strict_types=1);

// Error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    if (strpos($class, 'Scrapers\\') === 0) {
        $name = substr($class, strlen('Scrapers\\'));
        // Strip 'Scraper' suffix if looking for filename or check direct
        $file = __DIR__ . '/scrapers/' . $name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
        $stripped = preg_replace('/Scraper$/', '', $name);
        $file = __DIR__ . '/scrapers/' . $stripped . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

use App\Core\Cache;
use App\Core\CityRegistry;
use App\Core\Response;
use App\Helpers\Str;

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Istanbul');

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(['status' => 'ok'], 200);
}

// Read parameters
$cityParam     = $_GET['sehir'] ?? ($_GET['city'] ?? ($_GET['il'] ?? ''));
$districtParam = $_GET['ilce'] ?? ($_GET['district'] ?? null);
$refreshParam  = isset($_GET['refresh']) && in_array(strtolower((string)$_GET['refresh']), ['1', 'true', 'yes'], true);
$listParam     = isset($_GET['list']) || strtolower((string)$cityParam) === 'list';

$cache = new Cache(__DIR__ . '/cache', (int) ($config['cache_ttl'] ?? 1800));

// 1. List all provinces endpoint
if ($listParam) {
    $cities = CityRegistry::all();
    $list = [];
    foreach ($cities as $plate => $c) {
        $list[] = [
            'plaka'        => $plate,
            'sehir'        => $c['name'],
            'scraper_file' => 'scrapers/' . $c['file'],
            'kaynak_url'   => $c['url']
        ];
    }
    Response::json([
        'status'         => 'success',
        'toplam_sehir'   => count($list),
        'sehirler'       => $list,
        'kullanim_ornek' => [
            'sehir_sorgu'      => '?sehir=İstanbul',
            'ilce_sorgu'       => '?sehir=İstanbul&ilce=Kadıköy',
            'onbellek_yenile'  => '?sehir=İstanbul&refresh=1'
        ]
    ]);
}

// 2. Help / Documentation if no city given
if (empty($cityParam)) {
    Response::json([
        'status'      => 'info',
        'mesaj'       => 'Nöbetçi Eczane API hizmetine hoş geldiniz. Lütfen sorgulamak istediğiniz şehri belirtin.',
        'parametreler' => [
            'sehir'   => '(Zorunlu) İl adı veya plaka kodu (örn: İstanbul, ankara, 34, 06)',
            'ilce'    => '(Opsiyonel) İlçe filtresi (örn: Kadıköy, Çankaya, Bornova)',
            'refresh' => '(Opsiyonel) 1 veya true verilirse önbelleği temizleyip doğrudan kaynaktan çeker',
            'list'    => '(Opsiyonel) 1 verilirse tüm 81 ili listeler'
        ],
        'ornekler'    => [
            'İstanbul Tümü'          => '?sehir=İstanbul',
            'İstanbul Kadıköy'       => '?sehir=İstanbul&ilce=Kadıköy',
            'Ankara Çankaya'         => '?sehir=Ankara&ilce=Çankaya',
            'İzmir Konak'            => '?sehir=İzmir&ilce=Konak',
            'Bursa Nilüfer'          => '?sehir=Bursa&ilce=Nilüfer',
            'Tüm İlleri Listele'     => '?sehir=list'
        ]
    ]);
}

// 3. Find requested city
$cityData = CityRegistry::find((string)$cityParam);
if (!$cityData) {
    Response::error("Belirtilen şehir bulunamadı: '{$cityParam}'. Tüm şehir listesi için ?sehir=list adresini ziyaret edebilirsiniz.", 404);
}

$cityName = $cityData['name'];
$plateCode = $cityData['plate'];
$scraperFile = __DIR__ . '/scrapers/' . $cityData['file'];
$scraperClass = 'Scrapers\\' . $cityData['class'];

if (!file_exists($scraperFile)) {
    Response::error("Bu il için tanımlı scraper dosyası bulunamadı ({$cityData['file']}).", 500);
}

require_once $scraperFile;

if (!class_exists($scraperClass)) {
    Response::error("Scraper sınıfı bulunamadı ({$scraperClass}).", 500);
}

// 4. Cache resolution
$cleanDistrict = !empty($districtParam) ? Str::slug($districtParam) : 'all';
$cacheKey = "city_{$plateCode}_{$cleanDistrict}";

if (!$refreshParam && $config['cache_enabled']) {
    $cachedData = $cache->get($cacheKey);
    if ($cachedData !== null) {
        Response::success(
            $cachedData['pharmacies'],
            $cityName,
            !empty($districtParam) ? Str::titleTr($districtParam) : null,
            [
                'plaka'        => $plateCode,
                'cached'       => true,
                'cache_time'   => date('Y-m-d H:i:s', $cachedData['time']),
                'kaynak'       => $cityData['url'],
                'scraper_file' => 'scrapers/' . $cityData['file']
            ]
        );
    }
}

// 5. Run Scraper
try {
    /** @var \App\Core\BaseScraper $scraper */
    $scraper = new $scraperClass();
    $pharmacies = $scraper->scrape(!empty($districtParam) ? (string)$districtParam : null);

    // Save to cache
    if ($config['cache_enabled']) {
        $cache->set($cacheKey, [
            'time'       => time(),
            'pharmacies' => $pharmacies
        ], (int) $config['cache_ttl']);
    }

    Response::success(
        $pharmacies,
        $cityName,
        !empty($districtParam) ? Str::titleTr($districtParam) : null,
        [
            'plaka'        => $plateCode,
            'cached'       => false,
            'kaynak'       => $cityData['url'],
            'scraper_file' => 'scrapers/' . $cityData['file']
        ]
    );
} catch (\Throwable $e) {
    Response::error("Veri çekilirken bir hata oluştu: " . $e->getMessage(), 500, [
        'sehir'        => $cityName,
        'scraper_file' => 'scrapers/' . $cityData['file']
    ]);
}
