<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class IzmirScraper extends BaseScraper
{
    protected string $cityName = 'İzmir';
    protected int $plateCode = 35;
    protected string $primaryUrl = 'https://www.izmir.bel.tr/tr/NobetciEczane/27';
    protected string $apiUrl = 'https://openapi.izmir.bel.tr/api/ibb/nobetcieczaneler';

    public function scrape(?string $district = null): array
    {
        // 1. Primary: Official Izmir Open Data API
        $json = $this->fetchJson($this->apiUrl);
        $pharmacies = [];

        if (is_array($json) && !empty($json)) {
            foreach ($json as $item) {
                $rawDistrict = Str::titleTr($item['Bolge'] ?? '');
                
                if (!empty($district) && !Str::contains($rawDistrict, $district)) {
                    continue;
                }

                $lat = isset($item['LokasyonX']) && is_numeric($item['LokasyonX']) ? (float) $item['LokasyonX'] : null;
                $lng = isset($item['LokasyonY']) && is_numeric($item['LokasyonY']) ? (float) $item['LokasyonY'] : null;

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $item['Adi'] ?? '',
                    'district'   => $rawDistrict,
                    'address'    => $item['Adres'] ?? '',
                    'phone'      => $item['Telefon'] ?? '',
                    'directions' => $item['BolgeAciklama'] ?? '',
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'duty_hours' => 'Bugün 09:00 - Ertesi gün 09:00'
                ]);
            }

            if (!empty($pharmacies)) {
                return $this->filterPharmacies($pharmacies, $district);
            }
        }

        // 2. Fallback: Parse HTML from frontend
        $html = $this->fetchHtml($this->primaryUrl);
        if (preg_match_all('/<div class="nobetci-eczane">(.*?)<\/div>\s*<\/div>/is', $html, $cards)) {
            foreach ($cards[1] as $cardHtml) {
                if (preg_match('/<h2>(.*?)<\/h2>/i', $cardHtml, $nameMatch)) {
                    $name = Str::clean(strip_tags($nameMatch[1]));
                    preg_match('/<p class="nobetci-eczane-place">\s*(.*?)\s*<\/p>/is', $cardHtml, $addrMatch);
                    $addr = isset($addrMatch[1]) ? Str::clean(strip_tags($addrMatch[1])) : '';
                    
                    $phone = '';
                    if (preg_match('/href="tel:([^"]+)"/i', $cardHtml, $telMatch)) {
                        $phone = $telMatch[1];
                    }

                    $mapUrl = '';
                    if (preg_match('/href="(https?:\/\/www\.google\.com\/maps[^"]+)"/i', $cardHtml, $mMatch)) {
                        $mapUrl = $mMatch[1];
                    }

                    $coords = $this->extractCoordinates($mapUrl);

                    $pharmacies[] = $this->createPharmacy([
                        'name'      => $name,
                        'district'  => $district ?? '',
                        'address'   => $addr,
                        'phone'     => $phone,
                        'map_url'   => $mapUrl,
                        'latitude'  => $coords['lat'],
                        'longitude' => $coords['lng']
                    ]);
                }
            }
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
