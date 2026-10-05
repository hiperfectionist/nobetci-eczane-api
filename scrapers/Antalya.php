<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class AntalyaScraper extends BaseScraper
{
    protected string $cityName = 'Antalya';
    protected int $plateCode = 7;
    protected string $primaryUrl = 'https://www.antalyaeo.org.tr/tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Split by district headers: <img src="/Resim/Upload/beyaze.png"... /> <span> DISTRICT</span>
        $sections = preg_split('/beyaze\.png[^>]*>\s*<span>\s*([^<]+)\s*<\/span>/iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($sections) > 1) {
            for ($i = 1; $i < count($sections); $i += 2) {
                $distName = Str::titleTr(trim($sections[$i]));
                $distHtml = $sections[$i + 1] ?? '';

                // Match each .nobetciDiv in this district section
                preg_match_all('/<div[^>]*class=["\'][^"\']*nobetciDiv[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/isu', $distHtml, $cards);

                foreach ($cards[1] as $cHtml) {
                    // Extract name and phone from the first column
                    preg_match_all('/<a[^>]*href=["\']tel:([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', $cHtml, $tels);
                    
                    if (empty($tels[2])) {
                        continue;
                    }

                    $rawName = Str::clean(strip_tags($tels[2][0]));
                    $phone = $tels[1][0] ?? ($tels[1][1] ?? '');

                    // Extract map and address from .nadres link
                    $mapUrl = '';
                    $address = '';
                    if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*class=["\']nadres["\'][^>]*>(.*?)<\/a>/isu', $cHtml, $nadres)) {
                        $mapUrl = Str::clean($nadres[1]);
                        $address = Str::clean(strip_tags($nadres[2]));
                    }

                    $coords = $this->extractCoordinates($mapUrl);

                    $pharmacies[] = $this->createPharmacy([
                        'name'       => $rawName,
                        'district'   => $distName,
                        'address'    => $address,
                        'phone'      => $phone,
                        'map_url'    => $mapUrl,
                        'latitude'   => $coords['lat'],
                        'longitude'  => $coords['lng'],
                        'duty_hours' => 'Bugün 18:30 - Ertesi gün 08:30'
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
