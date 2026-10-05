<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SiirtScraper extends BaseScraper
{
    protected string $cityName = 'Siirt';
    protected int $plateCode = 56;
    protected string $primaryUrl = 'https://siirteo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.siirteo.org.tr/nobetci-eczaneler',
        'http://siirteo.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseCards($html);

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseCards(string $html): array
    {
        $pharmacies = [];

        $parts = preg_split('/<div class="col-md-12 nobetci">/i', $html);
        if (count($parts) > 1) {
            array_shift($parts); // Remove content prior to the first card
        } else {
            return [];
        }

        foreach ($parts as $p) {
            // 1. Name & District: <h4 class="tred"><strong>NEVAL ECZANESİ</strong> - Merkez</h4>
            $name = '';
            $district = '';
            if (preg_match('/<h4[^>]*>(.*?)<\/h4>/is', $p, $h4)) {
                $title = trim(strip_tags($h4[1]));
                if (str_contains($title, '-')) {
                    [$n, $d] = explode('-', $title, 2);
                    $name = trim($n);
                    $district = trim($d);
                } else {
                    $name = $title;
                }
            }
            $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);

            // 2. Phone
            $phone = '';
            if (preg_match('/<a[^>]*href=["\']tel:([^"\']+)["\']/is', $p, $ph)) {
                $phone = preg_replace('/[^\d]/', '', $ph[1]);
            }

            // 3. Map & Coords
            $mapUrl = '';
            $lat = null;
            $lng = null;
            if (preg_match('/<a[^>]*href=["\'](https?:\/\/(?:maps\.google\.com|www\.google\.com\/maps)[^"\']+)["\']/is', $p, $mapM)) {
                $mapUrl = html_entity_decode(trim($mapM[1]));
                if (preg_match('/q=([0-9.]+)[,%2C]+([0-9.]+)/i', $mapUrl, $coordM)) {
                    $lat = (float)$coordM[1];
                    $lng = (float)$coordM[2];
                }
            }

            // 4. Address: between fa-home and <br> or fa-phone
            $address = '';
            if (preg_match("/<i[^>]*class=['\"][^'\"]*fa-home[^'\"]*['\"][^>]*><\/i>(.*?)<br/is", $p, $addrM)) {
                $address = trim(strip_tags($addrM[1]));
            }

            if (!empty($cleanName)) {
                $item = [
                    'name'     => $cleanName,
                    'district' => Str::titleTr($district ?: 'Merkez'),
                    'address'  => $address,
                    'phone'    => $phone,
                ];

                if (!empty($mapUrl)) {
                    $item['maps_url'] = $mapUrl;
                }
                if ($lat !== null && $lng !== null) {
                    $item['latitude'] = $lat;
                    $item['longitude'] = $lng;
                }

                $pharmacies[] = $item;
            }
        }

        return $pharmacies;
    }
}
