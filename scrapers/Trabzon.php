<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;
use DOMDocument;
use DOMXPath;

class TrabzonScraper extends BaseScraper
{
    protected string $cityName = 'Trabzon';
    protected int $plateCode = 61;
    protected string $primaryUrl = 'https://www.trabzoneczaciodasi.org.tr/nobetci-eczaneler/61';
    protected array $fallbackUrls = [
        'https://trabzoneczaciodasi.org.tr/nobetci-eczaneler/61',
        'https://www.trabzoneczaciodasi.org.tr/'
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

        $pharmacies = $this->parseTrabzonCards($html);
        return $this->filterPharmacies($pharmacies, $district);
    }

    public function parseTrabzonCards(string $html): array
    {
        $pharmacies = [];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Trabzon Chamber cards have class "standart-post"
        $cards = $xpath->query("//div[contains(@class, 'standart-post')]");
        foreach ($cards as $card) {
            $h = $xpath->query(".//h2 | .//h1", $card)->item(0);
            $rawName = $h ? trim(preg_replace('/\s+/', ' ', $h->textContent)) : '';
            if (empty($rawName) || $this->isBlacklistedTitle($rawName)) {
                continue;
            }

            // District
            $cat = $xpath->query(".//a[contains(@class, 'category')]", $card)->item(0);
            $distName = $cat ? trim(preg_replace('/\s+/', ' ', $cat->textContent)) : '';
            if (empty($distName)) {
                $distName = 'MERKEZ';
            }

            // Duty hours
            $hours = '';
            $tagLi = $xpath->query(".//ul[contains(@class, 'post-tags')]//li", $card)->item(0);
            if ($tagLi) {
                $hours = trim(preg_replace('/\s+/', ' ', $tagLi->textContent));
            }

            // Phone
            $phone = '';
            $phoneNode = $xpath->query(".//a[starts-with(@href, 'tel:')]", $card)->item(0);
            if ($phoneNode) {
                $phone = trim($phoneNode->textContent);
            }

            // Map URL and coordinates
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
                if ($distName && ($posDist = mb_strpos($pText, $distName)) !== false) {
                    $pText = mb_substr($pText, $posDist + mb_strlen($distName));
                }
                if ($phone && ($posPhone = mb_strpos($pText, $phone)) !== false) {
                    $address = trim(mb_substr($pText, 0, $posPhone));
                } else {
                    $address = trim($pText);
                }
                $address = str_replace(['Haritada görüntülemek için tıklayınız...', 'Harita Konumu'], '', $address);
                $address = trim(preg_replace('/\s+/', ' ', $address));
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $rawName,
                'district'   => $distName,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => $hours,
                'latitude'   => $lat,
                'longitude'  => $lng,
                'map_url'    => $mapUrl,
            ]);
        }

        return $pharmacies;
    }
}
