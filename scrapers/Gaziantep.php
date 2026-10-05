<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class GaziantepScraper extends BaseScraper
{
    protected string $cityName = 'Gaziantep';
    protected int $plateCode = 27;
    protected string $primaryUrl = 'https://gaziantep.bel.tr/tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Match card headers and subsequent paragraphs
        preg_match_all('/<h2[^>]*class=["\'][^"\']*(?:font-bold|text-lg)[^"\']*["\'][^>]*>(.*?)<\/h2>(.*?)(?=<h2[^>]*class=["\']|\z)/isu', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $name = Str::clean(strip_tags($m[1]));
            $body = $m[2];

            if (empty($name) || Str::contains($name, 'Nöbetçi Eczaneler')) {
                continue;
            }

            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $tel)) {
                $phone = $tel[1];
            }

            $address = '';
            if (preg_match('/<strong>Adres:<\/strong>\s*(.*?)(?:<\/p>|<div)/isu', $body, $addr)) {
                $address = Str::clean(strip_tags($addr[1]));
            }

            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google)[^"\']+)["\']/i', $body, $map)) {
                $mapUrl = $map[1];
            }

            $coords = $this->extractCoordinates($mapUrl);

            // Infer district from address if mentioned (Şahinbey, Şehitkamil, Nizip, etc.)
            $dist = '';
            $knownDistricts = ['Şahinbey', 'Şehitkamil', 'Nizip', 'İslahiye', 'Nurdağı', 'Araban', 'Oğuzeli', 'Yavuzeli', 'Karkamış'];
            foreach ($knownDistricts as $kd) {
                if (Str::contains($address, $kd)) {
                    $dist = $kd;
                    break;
                }
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $address,
                'phone'      => $phone,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng'],
                'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
            ]);
        }

        if (empty($pharmacies)) {
            return $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
