<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class IspartaScraper extends BaseScraper
{
    protected string $cityName = 'Isparta';
    protected int $plateCode = 32;
    protected string $primaryUrl = 'https://www.ispartaeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://ispartaeo.org.tr/nobetci-eczaneler',
        'https://www.ispartaeo.org.tr/'
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

        $pharmacies = $this->parseIspartaCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseIspartaCards(string $html): array
    {
        $pharmacies = [];

        preg_match_all('/<div class="trend-content">(.*?)<\/div>\s*<\/div>/is', $html, $matches);

        foreach ($matches[1] as $block) {
            $name = '';
            $districtName = '';
            $address = '';
            $phone = '';
            $mapsUrl = '';

            if (preg_match('/<h3[^>]*>(.*?)<\/h3>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match('/<h5[^>]*>(.*?)<\/h5>/is', $block, $m)) {
                $districtName = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/<i class="fa fa-map-marker[^"]*"><\/i>(.*?)(?:<\/p>|<a)/is', $block, $m)) {
                $address = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/<i class="fa fa-phone[^"]*"><\/i>\s*([\d\s\-()]+)/is', $block, $m)) {
                $phone = trim(preg_replace('/[^\d]/', '', $m[1]));
            }

            if (preg_match('/href="([^"]*maps\.google\.com[^"]*)"/is', $block, $m)) {
                $mapsUrl = html_entity_decode($m[1]);
            }

            if (!empty($name)) {
                $pharmacy = [
                    'name' => $name,
                    'district' => $districtName,
                    'address' => $address,
                    'phone' => $phone,
                ];

                if (!empty($mapsUrl)) {
                    $pharmacy['maps_url'] = $mapsUrl;
                    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $mapsUrl, $coordMatches)) {
                        $pharmacy['latitude'] = (float) $coordMatches[1];
                        $pharmacy['longitude'] = (float) $coordMatches[2];
                    }
                }

                $pharmacies[] = $pharmacy;
            }
        }

        return $pharmacies;
    }
}
