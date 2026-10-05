<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KahramanmarasScraper extends BaseScraper
{
    protected string $cityName = 'Kahramanmaraş';
    protected int $plateCode = 46;
    protected string $primaryUrl = 'https://kahramanmaras.bel.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.kahramanmaras.bel.tr/nobetci-eczaneler',
        'https://kmaras.eo.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseMarasCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseMarasCards(string $html): array
    {
        $pharmacies = [];

        // Split by district titles
        $sections = preg_split('/<h1[^>]*class=["\']ms-pharmacy-title["\'][^>]*>(.*?)<\/h1>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($sections); $i += 2) {
            $districtRaw = trim(strip_tags($sections[$i]));
            $content = $sections[$i + 1] ?? '';

            preg_match_all('/<div class="eczane-row[^"]*">(.*?)<\/div>\s*<\/div>/is', $content, $rows);

            foreach ($rows[1] as $row) {
                $name = '';
                $address = '';
                $phone = '';
                $mapsUrl = '';

                if (preg_match('/<div class="eczane-td eczane-ad">(.*?)<\/div>/is', $row, $m)) {
                    $name = trim(html_entity_decode(strip_tags($m[1])));
                    $name = preg_replace('/\s*-\s*[^<]+$/ui', '', $name);
                    $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                }

                if (empty($name)) {
                    continue;
                }

                if (preg_match('/<div class="eczane-adres">(.*?)<\/div>/is', $row, $m)) {
                    $address = trim(html_entity_decode(strip_tags($m[1])));
                }

                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $row, $m)) {
                    $phone = preg_replace('/[^\d]/', '', $m[1]);
                    if (str_starts_with($phone, '90')) {
                        $phone = '0' . substr($phone, 2);
                    }
                }

                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $row, $m)) {
                    $mapsUrl = html_entity_decode($m[1]);
                }

                $pharmacy = [
                    'name'     => $name,
                    'district' => $districtRaw,
                    'address'  => $address,
                    'phone'    => $phone,
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
