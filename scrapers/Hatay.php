<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class HatayScraper extends BaseScraper
{
    protected string $cityName = 'Hatay';
    protected int $plateCode = 31;
    protected string $primaryUrl = 'https://www.hatayeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://hatayeo.org.tr/nobetci-eczaneler',
        'https://www.hatayeo.org.tr/'
    ];

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);

        if (empty($html)) {
            foreach ($this->fallbackUrls as $fallback) {
                $html = $this->fetchHtml($fallback);
                if (!empty($html)) {
                    break;
                }
            }
        }

        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Method 1: Parse ymaps Placemarks
        if (preg_match_all('/\.add\(new\s+ymaps\.Placemark\(\[([0-9.]+),\s*([0-9.]+)\]\s*,\s*\{\s*balloonContent:\s*[\'"](.*?)[\'"]\s*\}/is', $html, $matches)) {
            foreach ($matches[0] as $i => $full) {
                $lat = (float) $matches[1][$i];
                $lng = (float) $matches[2][$i];
                $balloon = $matches[3][$i];

                $name = '';
                if (preg_match('/<strong[^>]*>(.*?)<\/strong>/i', $balloon, $mName)) {
                    $name = trim(strip_tags($mName[1]));
                }

                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                $cleanBalloon = preg_replace('/<strong[^>]*>.*?<\/strong>/i', '', $balloon);
                $parts = array_values(array_filter(array_map('trim', explode('<br>', $cleanBalloon))));

                $dist = $parts[0] ?? '';
                $phone = $parts[1] ?? '';

                $mapUrl = "https://www.google.com/maps?q={$lat},{$lng}";
                $address = "{$dist} / Hatay";

                $pharmacies[] = $this->createPharmacy([
                    'name'      => $name,
                    'district'  => $dist,
                    'address'   => $address,
                    'phone'     => $phone,
                    'latitude'  => $lat,
                    'longitude' => $lng,
                    'map_url'   => $mapUrl
                ]);
            }
        }

        // Method 2 (Fallback): standard TEB cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
