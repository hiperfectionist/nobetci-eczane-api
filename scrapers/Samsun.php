<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SamsunScraper extends BaseScraper
{
    protected string $cityName = 'Samsun';
    protected int $plateCode = 55;
    protected string $primaryUrl = 'https://www.samsuneczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://samsuneczaciodasi.org.tr/nobetci-eczaneler',
        'https://www.samsuneo.org.tr/nobetci-eczaneler'
    ];

    private array $districtMap = [
        'İlkadım'        => 13,
        'Canik'          => 1851,
        'Atakum'         => 10,
        'Tekkeköy'       => 16,
        'Atakum Sayfiye' => 1800,
        'Alaçam'         => 20,
        'Asarcık'        => 32,
        'Ayvacık'        => 34,
        'Bafra'          => 19,
        'Çarşamba'       => 24,
        'Havza'          => 12,
        'Kavak'          => 18,
        'Ladik'          => 30,
        '19 Mayıs'       => 21,
        'Ondokuzmayıs'   => 21,
        'Salıpazarı'     => 40,
        'Terme'          => 22,
        'Vezirköprü'     => 28,
    ];

    public function scrape(?string $district = null): array
    {
        // 1. Initial GET to obtain session cookie & CSRF token
        $ch = curl_init($this->primaryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        ]);
        $mainResponse = (string)curl_exec($ch);
        curl_close($ch);

        $csrf = '';
        if (preg_match('/name=["\']_csrf["\']\s+value=["\']([^"\']+)["\']/i', $mainResponse, $csrfMatch)) {
            $csrf = $csrfMatch[1];
        }

        $cookie = '';
        if (preg_match('/set-cookie:\s*(PHPSESSID=[^;\r\n]+)/i', $mainResponse, $ckMatch)) {
            $cookie = $ckMatch[1];
        }

        if (empty($csrf)) {
            return [];
        }

        $today = date('Y-m-d');

        // Check if single district requested
        if (!empty($district)) {
            $matchedId = null;
            $matchedDistrictName = null;
            foreach ($this->districtMap as $dName => $id) {
                if (Str::contains($dName, $district) || Str::contains($district, $dName)) {
                    $matchedId = $id;
                    $matchedDistrictName = $dName;
                    break;
                }
            }

            if ($matchedId !== null) {
                $html = $this->postDistrict($matchedId, $csrf, $cookie, $today);
                $pharmacies = $this->parseCards($html, $matchedDistrictName);
                return $this->filterPharmacies($pharmacies, $district);
            }
        }

        // Parallel fetch of unique district IDs
        $uniqueDistricts = [];
        foreach ($this->districtMap as $name => $id) {
            if (!isset($uniqueDistricts[$id])) {
                $uniqueDistricts[$id] = $name;
            }
        }

        $mh = curl_multi_init();
        $handles = [];

        foreach ($uniqueDistricts as $id => $name) {
            $postData = [
                '_csrf'   => $csrf,
                'tarih1'  => $today,
                'tarih2'  => $today,
                'ilce'    => (string)$id,
                'gnr'     => 'ara'
            ];

            $ch = curl_init($this->primaryUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($postData),
                CURLOPT_COOKIE         => $cookie,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Referer: ' . $this->primaryUrl,
                    'Origin: https://www.samsuneczaciodasi.org.tr',
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);

        $allPharmacies = [];
        $seen = [];

        foreach ($handles as $id => $ch) {
            $content = (string)curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if (!empty($content)) {
                $cards = $this->parseCards($content, $uniqueDistricts[$id]);
                foreach ($cards as $p) {
                    $key = mb_strtolower($p['name'] . '_' . ($p['district'] ?? ''));
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $allPharmacies[] = $p;
                    }
                }
            }
        }

        curl_multi_close($mh);

        return $this->filterPharmacies($allPharmacies, $district);
    }

    private function postDistrict(int $districtId, string $csrf, string $cookie, string $date): string
    {
        $ch = curl_init($this->primaryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                '_csrf'   => $csrf,
                'tarih1'  => $date,
                'tarih2'  => $date,
                'ilce'    => (string)$districtId,
                'gnr'     => 'ara'
            ]),
            CURLOPT_COOKIE         => $cookie,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Referer: ' . $this->primaryUrl,
                'Origin: https://www.samsuneczaciodasi.org.tr',
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return (string)$res;
    }

    private function parseCards(string $html, string $fallbackDistrict): array
    {
        $pharmacies = [];

        preg_match_all('/<article class="nobet-card">(.*?)<\/article>/is', $html, $matches);
        foreach ($matches[1] as $cardHtml) {
            // Pharmacy name
            preg_match('/<span class="nobet-card-name">(.*?)<\/span>/is', $cardHtml, $nameM);
            $name = trim(strip_tags($nameM[1] ?? ''));
            $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);

            // District
            preg_match('/<span class="nobet-card-ilce">(.*?)<\/span>/is', $cardHtml, $ilceM);
            $ilce = trim(strip_tags($ilceM[1] ?? ''));
            if (empty($ilce)) {
                $ilce = $fallbackDistrict;
            }

            // Address
            preg_match('/<i class="fa-solid fa-house"[^>]*><\/i>\s*<span>(.*?)<\/span>/is', $cardHtml, $addrM);
            $address = trim(strip_tags($addrM[1] ?? ''));

            // Phone
            preg_match('/<a href="tel:([^"]+)"/is', $cardHtml, $phoneM);
            $phone = trim($phoneM[1] ?? '');
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);

            // Hours
            $dutyHours = '';
            if (preg_match('/<i class="fa-solid fa-clock"[^>]*><\/i>\s*<span>(.*?)<\/span>/is', $cardHtml, $clockM)) {
                $dutyHours = trim(preg_replace('/\s+/', ' ', strip_tags($clockM[1])));
            }

            // Map URL & coordinates
            $mapUrl = '';
            $lat = null;
            $lng = null;
            if (preg_match('/<a href="(https:\/\/www\.google\.com\/maps\?q=[^"]+)"/is', $cardHtml, $mapM)) {
                $mapUrl = html_entity_decode(trim($mapM[1]));
                if (preg_match('/maps\?q=([0-9.]+)[,%2C]+([0-9.]+)/i', $mapUrl, $coordM)) {
                    $lat = (float)$coordM[1];
                    $lng = (float)$coordM[2];
                }
            }

            if (!empty($cleanName)) {
                $item = [
                    'name'     => $cleanName,
                    'district' => Str::titleTr($ilce),
                    'address'  => $address,
                    'phone'    => $cleanPhone ?: $phone,
                ];

                if (!empty($dutyHours)) {
                    $item['duty_hours'] = $dutyHours;
                }
                if (!empty($mapUrl)) {
                    $item['maps_url'] = $mapUrl;
                }
                if ($lat !== null && $lng !== null) {
                    $item['latitude'] = $lat;
                    $item['longitude'] = $lng;
                }

                $pharmacies[] = $item;
            }
        }

        return $pharmacies;
    }
}
