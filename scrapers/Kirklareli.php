<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KirklareliScraper extends BaseScraper
{
    protected string $cityName = 'Kırklareli';
    protected int $plateCode = 39;
    protected string $primaryUrl = 'https://www.kirklarelieo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://kirklarelieo.org.tr/nobetci-eczaneler',
        'https://www.kirklarelieo.org.tr/'
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

        // Convert encoding if needed (site uses ISO-8859-9 / Turkish)
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-9');
        }

        $pharmacies = $this->parseKirklareliCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseKirklareliCards(string $html): array
    {
        $pharmacies = [];

        preg_match_all('/<div class="eleven columns bottom-1"[^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $matches);

        foreach ($matches[1] as $block) {
            $name = '';
            $districtName = '';
            $address = '';
            $phone = '';
            $mapsUrl = '';

            if (preg_match('/<font[^>]*class=["\']kirmizi["\'][^>]*>.*?<strong[^>]*>(?:<i[^>]*><\/i>)?\s*(.*?)\s*<\/strong>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match('/<i class=["\']icon-hand-right["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $districtName = trim(html_entity_decode(strip_tags($m[1])));
                if (in_array(strtoupper(Str::slug($districtName)), ['KIRKLARELI', 'MERKEZ', 'KIRKLARELI-MERKEZ'])) {
                    $districtName = 'Merkez';
                }
            }

            if (preg_match('/<i class=["\']icon-home["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $address = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/<i class=["\']icon-phone["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $phone = preg_replace('/[^\d]/', '', $m[1]);
            }

            if (preg_match('/href=["\'](https?:\/\/[^"\']*maps\.google\.com[^"\']*)["\']/is', $block, $m)) {
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
