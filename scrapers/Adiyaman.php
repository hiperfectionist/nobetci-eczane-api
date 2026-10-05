<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;
use DOMDocument;
use DOMXPath;

class AdiyamanScraper extends BaseScraper
{
    protected string $cityName = 'Adıyaman';
    protected int $plateCode = 2;
    protected string $primaryUrl = 'https://www.adiyamaneo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://adiyamaneo.org.tr/nobetci-eczaneler',
        'https://www.adiyamaneo.org.tr/'
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

        // Adiyaman Chamber uses .nobet-kart container cards
        $cards = $xpath->query("//*[contains(@class, 'nobet-kart')]");
        foreach ($cards as $card) {
            $h4 = $xpath->query(".//h4", $card)->item(0);
            $titleText = $h4 ? trim($h4->textContent) : '';
            if (empty($titleText)) {
                continue;
            }

            // Format: "ÖNER ECZANESİ - MERKEZ"
            $parts = explode('-', $titleText);
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
            $mapNode = $xpath->query(".//a[contains(@href, 'maps')]", $card)->item(0);
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
                    $lines = explode("\n", trim($pText));
                    $address = trim($lines[0] ?? '');
                }
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

        // Fallback to generic TEB cards if .nobet-kart structure wasn't found
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
