<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class RizeScraper extends BaseScraper
{
    protected string $cityName = 'Rize';
    protected int $plateCode = 53;
    protected string $primaryUrl = 'https://www.rize.bel.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://rize.bel.tr/nobetci-eczaneler',
        'https://rizeeczaciodasi.org.tr/nobetci-eczane'
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

        $pharmacies = $this->parseRizeGreenCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseRizeGreenCards(string $html): array
    {
        $pharmacies = [];

        // In Rize Municipality website, active on-duty pharmacies are designated by green styling: border-emerald-500
        preg_match_all('/<li[^>]*class=["\'][^"\']*border-emerald-500[^"\']*["\'][^>]*>(.*?)<\/li>/is', $html, $matches);

        // Fallback: if border-emerald-500 is not found, check items with 'Şu anda nöbetçi'
        if (empty($matches[1])) {
            preg_match_all('/<li[^>]*>(?=.*?Şu anda nöbetçi)(.*?)<\/li>/is', $html, $matches);
        }

        foreach ($matches[1] as $block) {
            $name = '';
            $districtName = '';
            $dutyHours = '';
            $phone = '';
            $mapsUrl = '';

            if (preg_match('/<p[^>]*leading-snug[^>]*>(.*?)<\/p>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match_all('/<p[^>]*text-ink-muted[^>]*>(.*?)<\/p>/is', $block, $mP)) {
                if (isset($mP[1][0])) {
                    $districtName = trim(html_entity_decode(strip_tags($mP[1][0])));
                }
                if (isset($mP[1][1])) {
                    $dutyHours = trim(html_entity_decode(strip_tags($mP[1][1])));
                }
            }

            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $block, $m)) {
                $phone = preg_replace('/[^\d]/', '', $m[1]);
            }

            if (preg_match('/href=["\'](https?:\/\/[^"\']*google\.com\/maps\/dir\/[^"\']*)["\']/is', $block, $m)) {
                $mapsUrl = html_entity_decode($m[1]);
            }

            if (!empty($name)) {
                $pharmacy = [
                    'name'       => $name,
                    'district'   => $districtName,
                    'address'    => $districtName . '/RİZE',
                    'phone'      => $phone,
                ];

                if (!empty($dutyHours)) {
                    $pharmacy['duty_hours'] = $dutyHours;
                }

                if (!empty($mapsUrl)) {
                    $pharmacy['maps_url'] = $mapsUrl;
                    if (preg_match('/destination=([0-9.-]+),([0-9.-]+)/', $mapsUrl, $c)) {
                        $pharmacy['latitude'] = (float)$c[1];
                        $pharmacy['longitude'] = (float)$c[2];
                    }
                }

                $pharmacies[] = $pharmacy;
            }
        }

        return $pharmacies;
    }
}
