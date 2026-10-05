<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class OrduScraper extends BaseScraper
{
    protected string $cityName = 'Ordu';
    protected int $plateCode = 52;
    protected string $primaryUrl = 'https://ordueczaciodasi.org.tr/nobetci-eczaneler/';
    protected array $fallbackUrls = [
        'https://www.ordueczaciodasi.org.tr/nobetci-eczaneler/',
        'https://ordueo.org.tr/nobetci-eczaneler'
    ];

    private array $districtIds = [
        'Altınordu'   => 701,
        'Fatsa'       => 706,
        'Ünye'        => 718,
        'Perşembe'    => 716,
        'Kumru'       => 714,
        'Korgan'      => 713,
        'Akkuş'       => 700,
        'Aybastı'     => 702,
        'Çamaş'       => 703,
        'Çatalpınar'  => 704,
        'Çaybaşı'     => 705,
        'Gölköy'      => 707,
        'Gülyalı'     => 708,
        'Gürgentepe'  => 709,
        'İkizce'      => 710,
        'Kabadüz'     => 711,
        'Kabataş'     => 712,
        'Mesudiye'    => 715,
        'Ulubey'      => 717,
    ];

    public function scrape(?string $district = null): array
    {
        // If a specific district was requested and matches a known district ID, fetch just that one
        if (!empty($district)) {
            $matchedId = null;
            $matchedName = null;
            foreach ($this->districtIds as $dName => $id) {
                if (Str::contains($dName, $district)) {
                    $matchedId = $id;
                    $matchedName = $dName;
                    break;
                }
            }

            if ($matchedId !== null) {
                $html = $this->fetchHtml("https://ordu.eczanesistemi.net/list/{$matchedId}");
                $pharmacies = $this->parseEczaneSistemiBlock($html, $matchedName);
                return $this->filterPharmacies($pharmacies, $district);
            }
        }

        // Otherwise fetch all in parallel with curl_multi
        $pharmacies = $this->fetchAllDistrictsParallel();

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function fetchAllDistrictsParallel(): array
    {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($this->districtIds as $name => $id) {
            $ch = curl_init("https://ordu.eczanesistemi.net/list/{$id}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$name] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $allPharmacies = [];

        foreach ($handles as $distName => $ch) {
            $html = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if (!empty($html)) {
                $parsed = $this->parseEczaneSistemiBlock($html, $distName);
                foreach ($parsed as $p) {
                    $allPharmacies[] = $p;
                }
            }
        }

        curl_multi_close($mh);
        return $allPharmacies;
    }

    private function parseEczaneSistemiBlock(string $html, string $districtName): array
    {
        $pharmacies = [];

        preg_match_all('/<div style="font-size:14px;[^"]*">(.*?)<\/div>/is', $html, $matches);
        foreach ($matches[1] as $block) {
            $name = '';
            $hours = '';
            $mapsUrl = '';
            $address = '';
            $directions = '';
            $phone = '';

            if (preg_match('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $block, $m)) {
                $mapsUrl = html_entity_decode($m[1]);
                $title = trim(strip_tags($m[2]));
                if (str_contains($title, ':')) {
                    [$h, $n] = explode(':', $title, 2);
                    $hours = trim($h);
                    $name = trim($n);
                } else {
                    $name = $title;
                }
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match_all('/<p>(.*?)<\/p>/is', $block, $pMatches)) {
                if (isset($pMatches[1][0])) {
                    $address = trim(strip_tags($pMatches[1][0]));
                }
                if (isset($pMatches[1][1])) {
                    $dirText = $pMatches[1][1];
                    if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $dirText, $ph)) {
                        $phone = preg_replace('/[^\d]/', '', $ph[1]);
                    }
                    $directions = trim(strip_tags(preg_replace('/-\s*<a.*$/is', '', $dirText)));
                }
            }

            if (!empty($name)) {
                $p = [
                    'name'     => $name,
                    'district' => $districtName,
                    'address'  => $address,
                    'phone'    => $phone,
                ];
                if (!empty($directions)) {
                    $p['directions'] = $directions;
                }
                if (!empty($hours)) {
                    $p['duty_hours'] = $hours;
                }
                if (!empty($mapsUrl)) {
                    $p['maps_url'] = $mapsUrl;
                    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $mapsUrl, $c)) {
                        $p['latitude'] = (float)$c[1];
                        $p['longitude'] = (float)$c[2];
                    }
                }
                $pharmacies[] = $p;
            }
        }

        return $pharmacies;
    }
}
