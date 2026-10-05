<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class UsakScraper extends BaseScraper
{
    protected string $cityName = 'Uşak';
    protected int $plateCode = 64;
    protected string $primaryUrl = 'https://usakeczaciodasi.org.tr/usak-nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.usakeczaciodasi.org.tr/usak-nobetci-eczaneler',
        'https://usakeczaciodasi.org.tr/'
    ];

    public function scrape(?string $district = null): array
    {
        $today = date('Y-m-d');
        $url = "{$this->primaryUrl}?date={$today}";

        $html = $this->fetchHtml($url);

        if (empty($html)) {
            $html = $this->fetchHtml($this->primaryUrl);
        }

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

        // Method 1: Data attributes on .pharmacy-card
        preg_match_all('/<div\s+class=["\']pharmacy-card["\']\s+([^>]+)>/is', $html, $cardTags);

        if (!empty($cardTags[1])) {
            foreach ($cardTags[1] as $attrs) {
                preg_match('/data-pharmacy-name=["\']([^"\']*)["\']/i', $attrs, $nm);
                preg_match('/data-pharmacy-district=["\']([^"\']*)["\']/i', $attrs, $dm);
                preg_match('/data-pharmacy-address=["\']([^"\']*)["\']/i', $attrs, $am);
                preg_match('/data-pharmacy-phone=["\']([^"\']*)["\']/i', $attrs, $pm);
                preg_match('/data-pharmacy-map=["\']([^"\']*)["\']/i', $attrs, $mm);

                $name = trim($nm[1] ?? '');
                $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                $dist = trim($dm[1] ?? 'Merkez');
                $addr = trim($am[1] ?? '');
                $phone = preg_replace('/[^\d]/', '', $pm[1] ?? '');
                if (strlen($phone) === 7) {
                    $phone = '0276' . $phone;
                }
                $mapUrl = trim($mm[1] ?? '');

                if (!empty($cleanName)) {
                    $item = [
                        'name'     => $cleanName,
                        'district' => Str::titleTr($dist),
                        'address'  => $addr,
                        'phone'    => $phone,
                    ];
                    if (!empty($mapUrl)) {
                        $item['maps_url'] = $mapUrl;
                    }
                    $pharmacies[] = $item;
                }
            }
        }

        // Method 2 Fallback: JSON-LD Structured Data
        if (empty($pharmacies)) {
            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $scripts);
            foreach ($scripts[1] as $s) {
                $data = json_decode($s, true);
                if (($data['@type'] ?? '') === 'Pharmacy') {
                    $name = trim($data['name'] ?? '');
                    $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                    $dist = trim($data['address']['addressLocality'] ?? 'Merkez');
                    $addr = trim($data['address']['streetAddress'] ?? '');
                    $phone = preg_replace('/[^\d]/', '', $data['telephone'] ?? '');
                    if (strlen($phone) === 7) {
                        $phone = '0276' . $phone;
                    }

                    if (!empty($cleanName)) {
                        $pharmacies[] = [
                            'name'     => $cleanName,
                            'district' => Str::titleTr($dist),
                            'address'  => $addr,
                            'phone'    => $phone,
                        ];
                    }
                }
            }
        }

        return $pharmacies;
    }
}
