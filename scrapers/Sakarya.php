<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SakaryaScraper extends BaseScraper
{
    protected string $cityName = 'Sakarya';
    protected int $plateCode = 54;
    protected string $primaryUrl = 'https://www.sakarya.bel.tr/a/EBelediye/NobetciEczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Match accordion items
        preg_match_all('/<span[^>]*class=["\']accordion__title["\'][^>]*>(.*?)<\/span>(.*?)(?=<span[^>]*class=["\']accordion__title|\z)/isu', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $titleRaw = Str::clean(strip_tags($m[1]));
            $body = $m[2];

            // Title format: "YAŞAM - ADAPAZARI"
            $parts = explode('-', $titleRaw);
            $name = Str::clean($parts[0]);
            $dist = isset($parts[1]) ? Str::titleTr(trim($parts[1])) : '';

            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $tel)) {
                $phone = $tel[1];
            }

            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?google\.com\/maps[^"\']+)["\']/i', $body, $map)) {
                $mapUrl = $map[1];
            }

            $address = '';
            if (preg_match('/<strong>Adres:<\/strong>\s*<a[^>]*>(.*?)<\/a>/isu', $body, $addr)) {
                $address = Str::clean(strip_tags($addr[1]));
            }

            $dutyHours = '';
            if (preg_match('/<strong>Nöbet Tarihi:<\/strong>\s*<span>(.*?)<\/span>/isu', $body, $dh)) {
                $dutyHours = Str::clean(strip_tags($dh[1]));
            }

            $coords = $this->extractCoordinates($mapUrl);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => $dutyHours,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng']
            ]);
        }

        if (empty($pharmacies)) {
            return $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
