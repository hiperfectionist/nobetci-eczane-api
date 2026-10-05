<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class ErzurumScraper extends BaseScraper
{
    protected string $cityName = 'Erzurum';
    protected int $plateCode = 25;
    protected string $primaryUrl = 'https://www.erzurumeo.org.tr/';
    protected string $sectionId = 'xx25';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = $this->parseErzurumSection($html, $this->sectionId);
        return $this->filterPharmacies($pharmacies, $district);
    }

    public function parseErzurumSection(string $html, string $secId): array
    {
        $needle = 'id="' . $secId . '"';
        $start = strpos($html, $needle);
        if ($start === false) {
            $needle = "id='{$secId}'";
            $start = strpos($html, $needle);
        }
        if ($start === false) {
            return $this->parseHeuristicCards($html, null);
        }

        $nextSec = strpos($html, 'id="xx', $start + strlen($needle) + 10);
        $sub = ($nextSec !== false) ? substr($html, $start, $nextSec - $start) : substr($html, $start, 10000);

        preg_match_all('/<h4[^>]*class=["\']kirmizi["\'][^>]*><strong>\s*([^<]+)\s*<\/strong><\/h4>(.*?)(?=<h4[^>]*class=["\']kirmizi|\z)/isu', $sub, $matches, PREG_SET_ORDER);

        $pharmacies = [];
        foreach ($matches as $m) {
            $name = Str::clean($m[1]);
            $body = $m[2];

            // District
            $districtName = '';
            if (preg_match('/icon-arrow-right[\'"][^>]*><\/i>\s*([^<]+)/iu', $body, $dMatch)) {
                $districtName = Str::titleTr(trim($dMatch[1]));
            }

            // Address
            $address = '';
            if (preg_match('/icon-home[\'"][^>]*><\/i>\s*([^<]+)/iu', $body, $aMatch)) {
                $address = Str::clean($aMatch[1]);
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
                'district'   => $districtName,
                'address'    => $address,
                'phone'      => $phone,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng'],
                'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
            ]);
        }

        return $pharmacies;
    }
}
