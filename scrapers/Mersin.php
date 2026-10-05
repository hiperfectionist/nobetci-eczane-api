<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class MersinScraper extends BaseScraper
{
    protected string $cityName = 'Mersin';
    protected int $plateCode = 33;
    protected string $primaryUrl = 'https://www.mersineczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://mersineczaciodasi.org.tr/nobetci-eczaneler',
        'https://www.mersineo.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseMersinCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseMersinCards(string $html): array
    {
        $pharmacies = [];

        $blocks = preg_split('/class="[^"]*nobet-kart[^"]*"/i', $html);
        array_shift($blocks);

        foreach ($blocks as $block) {
            $name = '';
            $districtName = '';
            $address = '';
            $directions = '';
            $phone = '';
            $mapsUrl = '';

            if (preg_match('/<h4[^>]*>\s*<strong>(.*?)<\/strong>(?:\s*-\s*([^<]+))?<\/h4>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                if (!empty($m[2])) {
                    $districtName = trim(html_entity_decode(strip_tags($m[2])));
                }
            }

            if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*(.*?)(?:<br|<\/p)/is', $block, $m)) {
                $address = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/<i class=[\'"]fa fa-arrows[^\'"]*[\'"]><\/i>\s*(.*?)(?:<br|<\/p)/is', $block, $m)) {
                $directions = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/href=[\'"]tel:([^\'"]+)[\'"]/i', $block, $m)) {
                $phone = preg_replace('/[^\d]/', '', $m[1]);
            }

            if (preg_match('/href=[\'"](https?:\/\/[^\'"]*maps[^\'"]*)[\'"]/i', $block, $m)) {
                $mapsUrl = html_entity_decode($m[1]);
            }

            if (!empty($name)) {
                $pharmacy = [
                    'name' => $name,
                    'district' => $districtName,
                    'address' => $address,
                    'directions' => $directions,
                    'phone' => $phone,
                ];

                if (!empty($mapsUrl)) {
                    $pharmacy['maps_url'] = $mapsUrl;
                    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $mapsUrl, $c)) {
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
