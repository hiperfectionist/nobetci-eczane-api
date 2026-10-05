<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;
use DOMDocument;
use DOMXPath;

class AmasyaScraper extends BaseScraper
{
    protected string $cityName = 'Amasya';
    protected int $plateCode = 5;
    protected string $primaryUrl = 'https://www.amasyaeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.nobetcieczane.org/amasya',
        'https://amasyaeo.org.tr/nobetci-eczaneler',
        'https://www.amasyaeo.org.tr/'
    ];

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);

        // Check if primaryUrl is blocked by Cloudflare or empty
        if (empty($html) || strpos($html, 'Just a moment') !== false || strpos($html, 'challenges.cloudflare.com') !== false) {
            $html = '';
            foreach ($this->fallbackUrls as $fallback) {
                $testHtml = $this->fetchHtml($fallback);
                if (!empty($testHtml) && strpos($testHtml, 'Just a moment') === false) {
                    $html = $testHtml;
                    break;
                }
            }
        }

        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Check if it's nobetcieczane.org format
        if (strpos($html, 'pharm-body') !== false || strpos($html, 'pharm-name') !== false) {
            $pharmacies = $this->parseNobetcieczaneOrg($html);
        } else {
            // Standard chamber portal layout
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $cards = $xpath->query("//*[contains(@class, 'nobetci')]");
            foreach ($cards as $card) {
                $h4 = $xpath->query(".//h4", $card)->item(0);
                if (!$h4) continue;

                $parts = explode('-', trim($h4->textContent));
                $rawName = trim($parts[0] ?? '');
                $distName = trim($parts[1] ?? 'MERKEZ');

                if ($this->isBlacklistedTitle($rawName)) continue;

                $phone = '';
                $phoneNode = $xpath->query(".//a[starts-with(@href, 'tel:')]", $card)->item(0);
                if ($phoneNode) $phone = trim($phoneNode->textContent);

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

                $address = '';
                $p = $xpath->query(".//p", $card)->item(0);
                if ($p) {
                    $pText = $p->textContent;
                    if ($phone && ($posPhone = mb_strpos($pText, $phone)) !== false) {
                        $address = trim(mb_substr($pText, 0, $posPhone));
                    } else {
                        $address = trim($pText);
                    }
                    $address = str_replace(['Harita Konumu', 'Haritada görüntülemek için tıklayınız...'], '', $address);
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
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    /**
     * Parse nobetcieczane.org card schema
     */
    protected function parseNobetcieczaneOrg(string $html): array
    {
        $pharmacies = [];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $cards = $xpath->query("//*[contains(@class, 'pharm-body')]");
        foreach ($cards as $card) {
            $nameNode = $xpath->query(".//span[contains(@class, 'pharm-name')]", $card)->item(0);
            $rawName = $nameNode ? trim($nameNode->textContent) : '';
            if (empty($rawName) || $this->isBlacklistedTitle($rawName)) {
                continue;
            }

            $addrNode = $xpath->query(".//*[contains(@class, 'pharm-address')]", $card)->item(0);
            $address = $addrNode ? trim($addrNode->textContent) : '';

            $district = 'Merkez';
            $phone = '';
            $dutyHours = '24 Saat';

            $metaSpans = $xpath->query(".//*[contains(@class, 'pharm-meta')]/span", $card);
            foreach ($metaSpans as $span) {
                $text = trim($span->textContent);
                if (mb_strpos($text, '·') !== false) {
                    $parts = explode('·', $text);
                    $district = trim($parts[1] ?? $parts[0]);
                } elseif (preg_match('/(?:\b0\s*\(?\d{3}\)?\s*[\d\s]{7,}\b)/', $text, $pm)) {
                    $phone = preg_replace('/[^\d]/', '', $pm[0]);
                } elseif (preg_match('/\d{2}:\d{2}\s*–\s*\d{2}:\d{2}/u', $text, $hm)) {
                    $dutyHours = trim(preg_replace('/\s+/', ' ', $hm[0]));
                }
            }

            if (empty($phone)) {
                $parent = $card->parentNode;
                $telNode = $xpath->query(".//a[starts-with(@href, 'tel:')]", $parent)->item(0);
                if ($telNode) {
                    $phone = preg_replace('/[^\d]/', '', $telNode->getAttribute('href'));
                }
            }

            $mapUrl = '';
            $lat = null;
            $lng = null;
            $parent = $card->parentNode;
            $mapNode = $xpath->query(".//a[contains(@href, 'google.com/maps') or contains(@href, 'maps.google')]", $parent)->item(0);
            if ($mapNode) {
                $mapUrl = $mapNode->getAttribute('href');
                if (preg_match('/q=([0-9\.\-]+),([0-9\.\-]+)/', $mapUrl, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                }
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $rawName,
                'district'   => $district,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => $dutyHours,
                'latitude'   => $lat,
                'longitude'  => $lng,
                'map_url'    => $mapUrl,
            ]);
        }

        return $pharmacies;
    }
}
