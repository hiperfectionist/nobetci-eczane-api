<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class AdanaScraper extends BaseScraper
{
    protected string $cityName = 'Adana';
    protected int $plateCode = 1;
    protected string $primaryUrl = 'https://www.adanaeo.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Split by district headers: <strong>DISTRICT BUGÜN NÖBETÇİ ECZANELER</strong>
        $sections = preg_split('/<strong>([a-zA-ZÇĞİÖŞÜçğıöşü\s]+)\s+BUGÜN NÖBETÇİ ECZANELER<\/strong>/iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($sections) > 1) {
            // First item is preamble before first district header
            for ($i = 1; $i < count($sections); $i += 2) {
                $distName = Str::titleTr(trim($sections[$i]));
                $distContent = $sections[$i + 1] ?? '';

                // Extract each pharmacy card within this district section
                preg_match_all('/<h4[^>]*class=["\']red["\'][^>]*><strong>\s*([^<]+)\s*<\/strong><\/h4>(.*?)(?=<h4[^>]*class=["\']red|\z)/isu', $distContent, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    $name = Str::clean($m[1]);
                    $body = $m[2];

                    $phone = '';
                    if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $tel)) {
                        $phone = $tel[1];
                    }

                    $mapUrl = '';
                    if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google)[^"\']+)["\']/i', $body, $map)) {
                        $mapUrl = $map[1];
                    }

                    $address = '';
                    if (preg_match('/fa-home[^>]*><\/i>\s*([^<]+)/iu', $body, $addr)) {
                        $address = Str::clean($addr[1]);
                    }

                    $coords = $this->extractCoordinates($mapUrl);

                    $pharmacies[] = $this->createPharmacy([
                        'name'       => $name,
                        'district'   => $distName,
                        'address'    => $address,
                        'phone'      => $phone,
                        'map_url'    => $mapUrl,
                        'latitude'   => $coords['lat'],
                        'longitude'  => $coords['lng'],
                        'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:00'
                    ]);
                }
            }
        }

        if (empty($pharmacies)) {
            return $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
