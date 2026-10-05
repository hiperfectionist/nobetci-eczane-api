<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SanliurfaScraper extends BaseScraper
{
    protected string $cityName = 'Şanlıurfa';
    protected int $plateCode = 63;
    protected string $primaryUrl = 'https://www.sanliurfaeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://sanliurfaeo.org.tr/nobetci-eczaneler',
        'https://portal.sanliurfaeo.birodam.org.tr/auth-server/api/duty-rosters/list-grouped'
    ];

    public function scrape(?string $district = null): array
    {
        $today = date('Y-m-d');
        $apiUrl = "https://portal.sanliurfaeo.birodam.org.tr/auth-server/api/duty-rosters/list-grouped?dutyDateFrom={$today}&dutyDateTo={$today}&size=100";

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Origin: https://www.sanliurfaeo.org.tr',
                'Referer: https://www.sanliurfaeo.org.tr/'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (empty($response)) {
            return [];
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            return [];
        }

        $rosters = $data['content'] ?? (is_array($data) ? $data : []);
        $pharmacies = [];

        foreach ($rosters as $r) {
            $regionName = $r['regionName'] ?? '';
            $dutyList = $r['dutyList'] ?? [];

            foreach ($dutyList as $duty) {
                $e = $duty['eczane'] ?? [];
                $name = trim($e['name'] ?? '');
                if (empty($name)) {
                    continue;
                }

                $cleanName = preg_replace('/\s*\(.*?\)/u', '', $name);
                $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', trim($cleanName));

                $dist = trim($e['district'] ?: $regionName);
                if (stripos($dist, 'ŞANLIURFA') !== false || empty($dist)) {
                    $dist = str_ireplace('ŞANLIURFA', '', $dist);
                    $dist = trim(str_replace(['-', '–'], '', $dist)) ?: 'Merkez';
                }

                $address = trim($e['location'] ?? '');
                $rawPhone = trim($e['phone'] ?? '');
                $phone = preg_replace('/[^\d]/', '', $rawPhone);
                if (strlen($phone) === 7) {
                    $phone = '0414' . $phone;
                }

                $lat = isset($e['latitude']) && is_numeric($e['latitude']) ? (float)$e['latitude'] : null;
                $lng = isset($e['longitude']) && is_numeric($e['longitude']) ? (float)$e['longitude'] : null;

                $item = [
                    'name'     => $cleanName,
                    'district' => Str::titleTr($dist),
                    'address'  => $address,
                    'phone'    => $phone ?: $rawPhone,
                ];

                if ($lat !== null && $lng !== null) {
                    $item['latitude'] = $lat;
                    $item['longitude'] = $lng;
                    $item['maps_url'] = "https://www.google.com/maps?q={$lat},{$lng}";
                }

                $pharmacies[] = $item;
            }
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
