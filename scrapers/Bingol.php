<?php

namespace Scrapers;

use App\Core\BaseScraper;
use DOMDocument;
use DOMXPath;

class BingolScraper extends BaseScraper
{
    protected string $cityName = 'Bingöl';
    protected int $plateCode = 12;
    protected string $primaryUrl = 'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler/bingol';
    protected array $fallbackUrls = [
        'https://elazigeczaciodasi.org.tr/nobetci-eczaneler/bingol',
        'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseElazigChamberCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    /**
     * Parse cards from Elazig Chamber portal (Elazig, Bingol, Tunceli)
     */
    public function parseElazigChamberCards(string $html): array
    {
        $pharmacies = [];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $cards = $xpath->query("//*[contains(@class, 'nobetci')]");
        foreach ($cards as $card) {
            $h4 = $xpath->query(".//h4", $card)->item(0);
            if (!$h4) {
                continue;
            }

            $title = trim(preg_replace('/\s+/', ' ', $h4->textContent));
            $parts = explode('-', $title);
            $rawName = trim($parts[0] ?? '');
            $distName = trim($parts[1] ?? 'MERKEZ');

            if ($this->isBlacklistedTitle($rawName)) {
                continue;
            }

            // Phone
            $phone = '';
            $phoneNode = $xpath->query(".//a[starts-with(@href, 'tel:')]", $card)->item(0);
            if ($phoneNode) {
                $phone = trim($phoneNode->textContent);
            }

            // Map and coordinates
            $mapUrl = '';
            $lat = null;
            $lng = null;
            $mapNode = $xpath->query(".//a[contains(@href, 'maps.google') or contains(@href, 'google.com/maps')]", $card)->item(0);
            if ($mapNode) {
                $mapUrl = $mapNode->getAttribute('href');
                if (preg_match('/q=([0-9\.\-]+),([0-9\.\-]+)/', $mapUrl, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                }
            }

            // Address
            $address = '';
            $p = $xpath->query(".//p", $card)->item(0);
            if ($p) {
                $pText = $p->textContent;
                if ($phone && ($posPhone = mb_strpos($pText, $phone)) !== false) {
                    $address = trim(mb_substr($pText, 0, $posPhone));
                } else {
                    $address = trim($pText);
                }
                $address = str_replace([
                    'Haritada görüntülemek için tıklayınız...',
                    'Eczaneyi haritada görüntülemek için tıklayınız...'
                ], '', $address);
                $address = trim(preg_replace('/\s+/', ' ', $address));
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $rawName,
                'district'   => $distName,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => '24 Saat',
                'latitude'   => $lat,
                'longitude'  => $lng,
                'map_url'    => $mapUrl,
            ]);
        }

        return $pharmacies;
    }
}
