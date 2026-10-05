<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SivasScraper extends BaseScraper
{
    protected string $cityName = 'Sivas';
    protected int $plateCode = 58;
    protected string $primaryUrl = 'https://www.sivaseo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://sivaseo.org.tr/nobetci-eczaneler',
        'https://www.sivaseo.org.tr/'
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

        $parts = preg_split('/<div class="team team-list clearfix">/i', $html);
        if (count($parts) > 1) {
            array_shift($parts);
        } else {
            return [];
        }

        foreach ($parts as $p) {
            // Pharmacy name
            $name = '';
            if (preg_match('/<h4[^>]*>(.*?)<\/h4>/is', $p, $nm)) {
                $name = trim(strip_tags($nm[1]));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            // District
            $district = '';
            if (preg_match('/<span><strong>(.*?)<\/strong><\/span>/is', $p, $dm)) {
                $district = trim(strip_tags($dm[1]));
            }

            // Address
            $address = '';
            if (preg_match('/<i class="icon-home[^"]*"><\/i>\s*(.*?)<br>/is', $p, $am)) {
                $address = trim(strip_tags($am[1]));
            }

            // Phone
            $phone = '';
            if (preg_match('/<a[^>]*href=["\']tel:([^"\']+)["\']/is', $p, $pm)) {
                $phone = preg_replace('/[^\d]/', '', $pm[1]);
            }

            // Map URL and coordinates
            $mapUrl = '';
            $lat = null;
            $lng = null;
            if (preg_match('/<a[^>]*href=["\'](https?:\/\/(?:maps\.google\.com|www\.google\.com\/maps)[^"\']+)["\']/is', $p, $mm)) {
                $mapUrl = html_entity_decode(trim($mm[1]));
                if (preg_match('/q=([0-9.]+)[,%2C]+([0-9.]+)/i', $mapUrl, $coordM)) {
                    $lat = (float)$coordM[1];
                    $lng = (float)$coordM[2];
                }
            }

            if (!empty($name)) {
                $item = [
                    'name'     => $name,
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
