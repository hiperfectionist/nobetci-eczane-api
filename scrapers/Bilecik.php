<?php

namespace Scrapers;

use App\Core\BaseScraper;
use DOMDocument;
use DOMXPath;

class BilecikScraper extends BaseScraper
{
    protected string $cityName = 'Bilecik';
    protected int $plateCode = 11;
    protected string $primaryUrl = 'https://www.eskisehireo.org.tr/bilecik-nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.eskisehireo.org.tr/bilecik-nobetci-eczaneler/',
        'https://eskisehireo.org.tr/bilecik-nobetci-eczaneler'
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

        $pharmacies = [];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Eskişehir chamber portal uses .nobetci cards
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

            // Duty hours / Clock
            $duty = '24 Saat';
            $clockSpan = $xpath->query(".//*[contains(@class, 'fa-clock-o')]/following-sibling::span", $card)->item(0);
            if ($clockSpan) {
                $duty = trim(preg_replace('/\s+/', ' ', $clockSpan->textContent));
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
                'duty_hours' => $duty,
                'latitude'   => $lat,
                'longitude'  => $lng,
                'map_url'    => $mapUrl,
            ]);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
