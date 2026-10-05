<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KirsehirScraper extends BaseScraper
{
    protected string $cityName = 'Kırşehir';
    protected int $plateCode = 40;
    protected string $primaryUrl = 'https://www.aksarayeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://aksarayeo.org.tr/nobetci-eczaneler',
        'https://www.kirsehireo.org.tr/nobetci-eczaneler'
    ];

    public function scrape(?string $district = null): array
    {
        $postData = [
            'tarih1' => date('d-m-Y'),
            'tarih2' => date('d-m-Y'),
            'ilce'   => '',
            'gnr'    => 'ARA',
        ];

        $html = $this->postHtml($this->primaryUrl, $postData);

        if (empty($html)) {
            foreach ($this->fallbackUrls as $fallback) {
                $html = $this->postHtml($fallback, $postData);
                if (!empty($html)) {
                    break;
                }
            }
        }

        if (empty($html)) {
            $html = $this->fetchHtml($this->primaryUrl);
        }

        if (empty($html)) {
            return [];
        }

        $pharmacies = $this->parseKirsehirCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseKirsehirCards(string $html): array
    {
        $pharmacies = [];

        preg_match_all('/<div class="eleven columns bottom-2">(.*?)<\/div>/is', $html, $matches);

        foreach ($matches[1] as $block) {
            $name = '';
            $districtName = '';
            $address = '';
            $phone = '';
            $mapsUrl = '';

            // Check location tag: e.g. 06.10.2026 / KAMAN/KIRŞEHİR or 06.10.2026 / KIRŞEHİR
            if (preg_match('/<i class="fa fa-calendar"><\/i>\s*[\d.]+\s*\/\s*([^<]+)/is', $block, $mLoc)) {
                $locationRaw = trim($mLoc[1]);
                // Only keep pharmacies belonging to Kırşehir
                if (stripos($locationRaw, 'KIRŞEHİR') === false && stripos($locationRaw, 'KIRSEHIR') === false) {
                    continue;
                }

                $parts = explode('/', $locationRaw);
                if (count($parts) > 1) {
                    $districtName = trim($parts[0]);
                } else {
                    $districtName = 'Merkez';
                }
            } else {
                continue;
            }

            if (preg_match('/<strong>(?:<i[^>]*><\/i>)?\s*(.*?)\s*<\/strong>/is', $block, $mName)) {
                $name = trim(html_entity_decode(strip_tags($mName[1])));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*(.*?)(?:<i class=[\'"]fa fa-phone|<br|<\/p)/is', $block, $mAddr)) {
                $address = trim(html_entity_decode(strip_tags($mAddr[1])));
            }

            if (preg_match('/href=[\'"]tel:([^\'"]+)[\'"]/i', $block, $mPhone)) {
                $phone = preg_replace('/[^\d]/', '', $mPhone[1]);
            }

            if (preg_match('/href=[\'"](https?:\/\/[^\'"]*maps[^\'"]*)[\'"]/i', $block, $mMaps)) {
                $mapsUrl = html_entity_decode($mMaps[1]);
            }

            if (!empty($name)) {
                $pharmacy = [
                    'name'     => $name,
                    'district' => $districtName,
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
