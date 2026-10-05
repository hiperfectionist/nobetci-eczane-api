<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class NigdeScraper extends BaseScraper
{
    protected string $cityName = 'Niğde';
    protected int $plateCode = 51;
    protected string $primaryUrl = 'https://www.neo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://neo.org.tr/nobetci-eczaneler',
        'https://www.nigdeeo.org.tr/nobetci-eczaneler'
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

        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-9');
        }

        $pharmacies = $this->parseNigdeCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseNigdeCards(string $html): array
    {
        $pharmacies = [];

        $blocks = preg_split('/class="eight columns bottom-1"/i', $html);
        array_shift($blocks);

        foreach ($blocks as $block) {
            $name = '';
            $districtName = '';
            $address = '';
            $phone = '';
            $mapsUrl = '';

            if (preg_match('/<font[^>]*class=["\']kirmizi["\'][^>]*>.*?<strong[^>]*>(?:<i[^>]*><\/i>)?\s*(.*?)\s*<\/strong>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (empty($name) || $this->isBlacklistedTitle($name)) {
                continue;
            }

            if (preg_match('/<i class=["\']icon-hand-right["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $districtName = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/<i class=["\']icon-home["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $address = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $block, $m)) {
                $phone = preg_replace('/[^\d]/', '', $m[1]);
            }

            if (preg_match('/href=["\'](https?:\/\/[^"\']*maps\.google\.com[^"\']*)["\']/is', $block, $m)) {
                $mapsUrl = html_entity_decode($m[1]);
            }

            $pharmacy = [
                'name'       => $name,
                'district'   => $districtName,
                'address'    => $address,
                'phone'      => $phone,
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

        return $pharmacies;
    }
}
