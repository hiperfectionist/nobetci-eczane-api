<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class MalatyaScraper extends BaseScraper
{
    protected string $cityName = 'Malatya';
    protected int $plateCode = 44;
    protected string $primaryUrl = 'https://www.malatyaeczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://malatyaeczaciodasi.org.tr/nobetci-eczaneler',
        'https://www.malatyaeo.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseMalatyaCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseMalatyaCards(string $html): array
    {
        $pharmacies = [];

        // Match cards with nobetciecz
        preg_match_all('/<div[^>]*class=["\'][^"\']*nobetciecz[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $matches);

        foreach ($matches[1] as $block) {
            $name = '';
            $districtName = 'Merkez';
            $address = '';
            $phone = '';
            $hours = '';

            if (preg_match('/<h4[^>]*class=["\']red border["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/h4>/is', $block, $m)) {
                $name = trim(html_entity_decode(strip_tags($m[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (empty($name)) {
                continue;
            }

            if (preg_match('/<i class=["\']fa fa-home[^\'"]*["\']><\/i>\s*(.*?)(?:<br|<\/p)/is', $block, $m)) {
                $address = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $block, $m)) {
                $phone = preg_replace('/[^\d]/', '', $m[1]);
            }

            if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*([^<]+)/is', $block, $m)) {
                $hours = trim(html_entity_decode(strip_tags($m[1])));
            }

            // Detect district from address or defaults
            if (stripos($address, 'BATTALGAZİ') !== false || stripos($address, 'BATTALGAZI') !== false) {
                $districtName = 'Battalgazi';
            } elseif (stripos($address, 'YEŞİLYURT') !== false || stripos($address, 'YESILYURT') !== false) {
                $districtName = 'Yeşilyurt';
            } elseif (stripos($address, 'ESKİMALATYA') !== false || stripos($address, 'ESKI MALATYA') !== false) {
                $districtName = 'Eskimalatya';
            }

            $pharmacy = [
                'name'       => $name,
                'district'   => $districtName,
                'address'    => $address,
                'phone'      => $phone,
            ];

            if (!empty($hours)) {
                $pharmacy['duty_hours'] = $hours;
            }

            $pharmacies[] = $pharmacy;
        }

        return $pharmacies;
    }
}
