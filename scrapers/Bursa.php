<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class BursaScraper extends BaseScraper
{
    protected string $cityName = 'Bursa';
    protected int $plateCode = 16;
    protected string $primaryUrl = 'https://www.beo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = ['https://www.bursaecza.org.tr/'];

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            $html = $this->fetchHtml($this->fallbackUrls[0]);
        }

        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Match h4 red blocks
        preg_match_all('/<h4[^>]*class=["\']red["\'][^>]*><strong>\s*([^<]+)\s*<\/strong>(?:\s*-\s*([^<]+))?<\/h4>(.*?)(?=<h4[^>]*class=["\']red|\z)/isu', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $name = Str::clean($m[1]);
            $dist = isset($m[2]) ? Str::titleTr(trim($m[2])) : '';
            $body = $m[3];

            // Address: text following fa-home
            $address = '';
            if (preg_match('/fa-home[^>]*><\/i>\s*([^<]+)/iu', $body, $aMatch)) {
                $address = Str::clean($aMatch[1]);
            }

            // Directions in parentheses
            $directions = '';
            if (preg_match('/\(([^)]+)\)/u', $body, $dirMatch)) {
                $directions = Str::clean($dirMatch[1]);
            }

            // Phone
            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $tel)) {
                $phone = $tel[1];
            }

            // Map URL
            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google)[^"\']+)["\']/i', $body, $map)) {
                $mapUrl = $map[1];
            }

            $coords = $this->extractCoordinates($mapUrl);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $address,
                'directions' => $directions,
                'phone'      => $phone,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng'],
                'duty_hours' => 'Bugün 18:30 - Ertesi gün 08:30'
            ]);
        }

        if (empty($pharmacies)) {
            return $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
