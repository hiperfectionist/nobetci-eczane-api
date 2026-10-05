<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KonyaScraper extends BaseScraper
{
    protected string $cityName = 'Konya';
    protected int $plateCode = 42;
    protected string $primaryUrl = 'https://www.konyanobetcieczaneleri.com/';
    protected array $fallbackUrls = ['https://www.keo.org.tr/'];

    public function scrape(?string $district = null): array
    {
        $cookieFile = sys_get_temp_dir() . '/konya_cookie_' . uniqid() . '.txt';

        $ch = curl_init($this->primaryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $html = curl_exec($ch);

        $pharmacies = [];

        if ($html) {
            // 1. Extract pharmacy cards metadata from HTML
            preg_match_all('/<article[^>]*data-ad=["\']([^"\']+)["\'][^>]*data-bolge=["\']([^"\']+)["\'][^>]*data-anahtar=["\']([^"\']+)["\'][^>]*>/isu', $html, $articles, PREG_SET_ORDER);

            // 2. Fetch dynamic details via POST endpoint
            preg_match('/data-veri=["\']([^"\']+)["\']/i', $html, $vMatch);
            preg_match('/data-j=["\']([^"\']+)["\']/i', $html, $jMatch);

            $details = [];
            if (!empty($vMatch[1]) && !empty($jMatch[1])) {
                $endpointUrl = "https://www.konyanobetcieczaneleri.com" . $vMatch[1];
                curl_setopt_array($ch, [
                    CURLOPT_URL        => $endpointUrl,
                    CURLOPT_POST       => true,
                    CURLOPT_POSTFIELDS => http_build_query(['j' => $jMatch[1], 'cf' => '']),
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'X-Requested-With: XMLHttpRequest',
                        'Referer: https://www.konyanobetcieczaneleri.com/'
                    ]
                ]);
                $postRes = curl_exec($ch);
                if ($postRes) {
                    $details = @json_decode($postRes, true) ?: [];
                }
            }
            curl_close($ch);
            @unlink($cookieFile);

            foreach ($articles as $art) {
                $name = Str::clean($art[1]);
                $dist = Str::clean($art[2]);
                $key  = Str::clean($art[3]);

                $det = $details[$key] ?? [];

                $address = $det['adres'] ?? '';
                $phone = $det['tel'] ?? ($det['telLink'] ?? '');
                $directions = $det['tarif'] ?? '';
                $lat = isset($det['lat']) ? (float) $det['lat'] : null;
                $lng = isset($det['lon']) ? (float) $det['lon'] : null;

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $dist,
                    'address'    => $address,
                    'phone'      => $phone,
                    'directions' => $directions,
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'duty_hours' => 'Bugün 08:30 - Ertesi gün 08:30'
                ]);
            }
        } else {
            curl_close($ch);
            @unlink($cookieFile);
        }

        if (empty($pharmacies)) {
            $fallbackHtml = $this->fetchHtml($this->fallbackUrls[0]);
            return $this->parseHeuristicCards($fallbackHtml, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
