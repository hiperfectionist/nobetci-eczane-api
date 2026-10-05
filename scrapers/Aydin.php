<?php

namespace Scrapers;

use App\Core\BaseScraper;
use DOMDocument;
use DOMXPath;

class AydinScraper extends BaseScraper
{
    protected string $cityName = 'Aydın';
    protected int $plateCode = 9;
    protected string $primaryUrl = 'https://www.aydineczaciodasi.org.tr/2nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.aydineczaciodasi.org.tr/nobetci-eczaneler',
        'https://aydineczaciodasi.org.tr/2nobetci-eczaneler',
        'https://www.aydineczaciodasi.org.tr/'
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

        // Aydin Chamber groups pharmacies under .twelve.columns divs
        $elements = $xpath->query("//div[contains(@class, 'twelve columns')]");
        $currentDistrict = 'MERKEZ';

        foreach ($elements as $el) {
            // District header check (e.g. EFELER, KUŞADASI, SÖKE, NAZİLLİ, DİDİM)
            $h4 = $xpath->query(".//h4[contains(@class, 'bottom-2') or contains(@style, 'color:#359da0')]", $el)->item(0);
            if ($h4) {
                $dist = trim(strip_tags($h4->textContent));
                if (!empty($dist)) {
                    $currentDistrict = $dist;
                }
                continue;
            }

            // Pharmacy card check (has font with class kirmizi)
            $nameFont = $xpath->query(".//font[contains(@class, 'kirmizi')]", $el)->item(0);
            if (!$nameFont) {
                continue;
            }

            $rawName = trim(strip_tags($nameFont->textContent));
            $rawName = preg_replace('/\s+/', ' ', $rawName);
            if (empty($rawName) || $this->isBlacklistedTitle($rawName)) {
                continue;
            }

            // Address and Phone
            $address = '';
            $phone = '';
            $lat = null;
            $lng = null;
            $mapUrl = '';

            $pNodes = $xpath->query(".//p", $el);
            foreach ($pNodes as $p) {
                $text = $p->textContent;
                // Phone detection
                if (preg_match('/(?:0\s*[2-5]\d{2}\s*\d{3}\s*\d{2}\s*\d{2}|0\d{10})/', $text, $pm)) {
                    $phone = preg_replace('/[^\d]/', '', $pm[0]);
                }

                // Address detection
                $lines = array_filter(array_map('trim', explode("\n", $text)));
                foreach ($lines as $line) {
                    if (preg_match('/(?:Mah|Cad|Sok|No:|Bulv|Hastane|Köy|Mevki|Karşısı|Yanı|Caddesi|Sokağı)/iu', $line)) {
                        if (empty($address)) {
                            $address = $line;
                        }
                    }
                }
            }

            // Map URL and coordinates
            $mapNode = $xpath->query(".//a[contains(@href, 'maps.google') or contains(@href, 'google.com/maps')]", $el)->item(0);
            if ($mapNode) {
                $mapUrl = $mapNode->getAttribute('href');
                if (preg_match('/q=([0-9\.\-]+),([0-9\.\-]+)/', $mapUrl, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                }
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $rawName,
                'district'   => $currentDistrict,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => '24 Saat',
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
