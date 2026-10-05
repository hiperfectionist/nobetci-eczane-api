<?php

namespace Scrapers;

use App\Core\BaseScraper;
use DOMDocument;
use DOMXPath;

class BalikesirScraper extends BaseScraper
{
    protected string $cityName = 'Balıkesir';
    protected int $plateCode = 10;
    protected string $primaryUrl = 'https://www.balikesireczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://balikesireczaciodasi.org.tr/nobetci-eczaneler',
        'https://www.balikesireczaciodasi.org.tr/'
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

        // Balıkesir Chamber uses .nobetciecz class for pharmacy cards
        $cards = $xpath->query("//*[contains(@class, 'nobetciecz')]");
        foreach ($cards as $card) {
            $h4 = $xpath->query(".//h4", $card)->item(0);
            if (!$h4) {
                continue;
            }

            $titleText = trim(preg_replace('/\s+/', ' ', $h4->textContent));
            $parts = explode('-', $titleText);
            $rawName = trim($parts[0] ?? '');
            $distName = trim($parts[1] ?? 'MERKEZ');

            if ($this->isBlacklistedTitle($rawName)) {
                continue;
            }

            // Extract custom duty hours if present in district string (e.g. "BURHANİYE GECE 23:59 ´A KADAR NÖBETÇİDİR.")
            $dutyHours = '24 Saat';
            if (preg_match('/(GECE[^\n<]+NÖBETÇİ[^\n<]*)/iu', $distName, $dm)) {
                $dutyHours = trim($dm[1]);
                $distName = trim(str_replace($dm[1], '', $distName));
            }
            $distName = trim(preg_replace('/\s+/', ' ', $distName));

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
            $mapNode = $xpath->query(".//a[contains(@href, 'google.com/maps') or contains(@href, 'maps.google')]", $card)->item(0);
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
                'duty_hours' => $dutyHours,
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
