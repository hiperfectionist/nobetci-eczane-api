<?php

namespace Scrapers;

use App\Core\BaseScraper;
use DOMDocument;
use DOMXPath;

class BitlisScraper extends BaseScraper
{
    protected string $cityName = 'Bitlis';
    protected int $plateCode = 13;
    protected string $primaryUrl = 'https://www.bitlisecza.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://bitlisecza.org.tr/nobetci-eczaneler',
        'https://www.bitlisecza.org.tr/'
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

        // Bitlis uses .nobetci cards with district in <small>
        $cards = $xpath->query("//*[contains(@class, 'nobetci')]");
        foreach ($cards as $card) {
            $h4 = $xpath->query(".//h4", $card)->item(0);
            if (!$h4) {
                continue;
            }

            // Extract district from <small> tag inside <h4>
            $small = $xpath->query(".//small", $h4)->item(0);
            $dist = $small ? trim($small->textContent) : 'MERKEZ';

            // Extract name from <strong> without <small> content
            $name = '';
            $strong = $xpath->query(".//strong", $h4)->item(0);
            if ($strong) {
                $strongClone = $strong->cloneNode(true);
                $smallsInClone = $strongClone->getElementsByTagName('small');
                while ($smallsInClone->length > 0) {
                    $smallsInClone->item(0)->parentNode->removeChild($smallsInClone->item(0));
                }
                $name = trim(preg_replace('/\s+/', ' ', $strongClone->textContent));
            } else {
                $name = trim(preg_replace('/\s+/', ' ', $h4->textContent));
            }

            if (empty($name) || $this->isBlacklistedTitle($name)) {
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
                'name'       => $name,
                'district'   => $dist,
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
