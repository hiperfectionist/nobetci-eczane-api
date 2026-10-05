<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class AnkaraScraper extends BaseScraper
{
    protected string $cityName = 'Ankara';
    protected int $plateCode = 6;
    protected string $primaryUrl = 'https://www.aeo.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $today = date('Y-m-d');
        $apiUrl = "https://www.aeo.org.tr/getPharmacies/{$today}";

        $json = $this->fetchJson($apiUrl, [
            'Referer: https://www.aeo.org.tr/nobetci-eczaneler',
            'X-Requested-With: XMLHttpRequest'
        ]);

        $html = '';
        if ($json && isset($json['html'])) {
            $html = $json['html'];
        } else {
            $html = $this->fetchHtml($this->primaryUrl);
        }

        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Parse inline-box entries from AEO HTML response
        preg_match_all('/<div[^>]*class=["\'][^"\']*inline-box[^"\']*["\'][^>]*data-name=["\']([^"\']+)["\'][^>]*data-district=["\']([^"\']+)["\'][^>]*>(.*?)<\/div>\s*(?=<div[^>]*class=["\'][^"\']*inline-box|\s*<\/div>\s*<\/div>|\z)/isu', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $rawName = Str::clean($m[1]);
            $rawDistrict = Str::clean($m[2]);
            $boxHtml = $m[3];

            // Extract phone
            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $boxHtml, $tel)) {
                $phone = $tel[1];
            }

            // Extract Map URL
            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google)[^"\']+)["\']/i', $boxHtml, $map)) {
                $mapUrl = $map[1];
            }

            // Extract Address
            $address = '';
            if (preg_match('/<div[^>]*class=["\'][^"\']*address[^"\']*["\'][^>]*>(.*?)<\/div>/isu', $boxHtml, $addr)) {
                $address = Str::clean(strip_tags($addr[1]));
            } elseif (preg_match('/<p[^>]*class=["\'][^"\']*desc[^"\']*["\'][^>]*>(.*?)<\/p>/isu', $boxHtml, $addr)) {
                $address = Str::clean(strip_tags($addr[1]));
            } else {
                $text = strip_tags($boxHtml);
                $lines = array_filter(array_map('trim', explode("\n", $text)));
                foreach ($lines as $line) {
                    if (preg_match('/(?:Mah|Cad|Sok|No:|Bulv)/iu', $line)) {
                        $address = $line;
                        break;
                    }
                }
            }

            // Extract duty hours
            $dutyHours = '';
            if (preg_match('/(?:Nöbet Saatleri|Saat|Saatler)\s*:\s*([^<]+)/iu', $boxHtml, $dh)) {
                $dutyHours = Str::clean($dh[1]);
            }

            $coords = $this->extractCoordinates($mapUrl);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $rawName,
                'district'   => $rawDistrict,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => $dutyHours ?: '08:30 - Ertesi gün 08:30',
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng']
            ]);
        }

        if (empty($pharmacies)) {
            // Heuristic fallback
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
