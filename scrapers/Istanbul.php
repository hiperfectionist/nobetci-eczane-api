<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class IstanbulScraper extends BaseScraper
{
    protected string $cityName = 'İstanbul';
    protected int $plateCode = 34;
    protected string $primaryUrl = 'https://eczane.ibb.gov.tr/';
    protected string $apiUrl = 'https://eczane.ibb.gov.tr/api/pharmacy/getpharmacies';

    public function scrape(?string $district = null): array
    {
        // 1. Primary: IBB Official JSON Geo-API
        $json = $this->fetchJson($this->apiUrl, [
            'Referer: https://eczane.ibb.gov.tr/',
            'X-Requested-With: XMLHttpRequest'
        ]);

        $pharmacies = [];

        if ($json && isset($json['features']) && is_array($json['features'])) {
            foreach ($json['features'] as $feature) {
                $prop = $feature['properties'] ?? [];
                
                // Only on-duty pharmacies (nightDuty = 1)
                if (empty($prop['nightDuty'])) {
                    continue;
                }

                $districtName = Str::titleTr($prop['districtName'] ?? '');
                
                // District filter
                if (!empty($district) && !Str::contains($districtName, $district)) {
                    continue;
                }

                $coords = $feature['geometry']['coordinates'] ?? [null, null];
                $lng = isset($coords[0]) ? (float) $coords[0] : null;
                $lat = isset($coords[1]) ? (float) $coords[1] : null;

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $prop['pharmacyName'] ?? '',
                    'district'   => $districtName,
                    'address'    => ($prop['address'] ?? '') . (!empty($prop['neighborhoodName']) ? ' (' . Str::titleTr($prop['neighborhoodName']) . ' Mah.)' : ''),
                    'phone'      => $prop['phoneNumber'] ?? '',
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'duty_hours' => 'Bugün 18:30 - Yarın 08:30'
                ]);
            }

            if (!empty($pharmacies)) {
                return $this->filterPharmacies($pharmacies, $district);
            }
        }

        // 2. Fallback: Parse HTML from frontend if API format ever changes
        $html = $this->fetchHtml($this->primaryUrl);
        return $this->parseTebCards($html, $district);
    }
}
