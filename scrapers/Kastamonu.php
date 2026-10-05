<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KastamonuScraper extends BaseScraper
{
    protected string $cityName = 'Kastamonu';
    protected int $plateCode = 37;
    protected string $primaryUrl = 'https://www.kastamonueo.org.tr/nobetci-eczaneler/37';
    protected array $fallbackUrls = [
        'https://www.kastamonueo.org.tr/nobetci-eczaneler',
        'https://kastamonueo.org.tr/nobetci-eczaneler/37',
        'https://kastamonueo.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseKastamonuCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseKastamonuCards(string $html): array
    {
        $pharmacies = [];

        if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobetci[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $cards)) {
            foreach ($cards[1] as $cardBody) {
                $name = '';
                $distName = '';
                if (preg_match('/<h4[^>]*class=["\']tred["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>(?:\s*-\s*([^<]+))?<\/h4>/is', $cardBody, $mH4)) {
                    $name = trim(strip_tags($mH4[1]));
                    $distName = isset($mH4[2]) ? trim(strip_tags($mH4[2])) : '';
                } elseif (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $cardBody, $mH)) {
                    $name = trim(strip_tags($mH[1]));
                }

                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);

                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                // Skip non-Kastamonu branches if scraped from general page
                if (stripos($distName, 'ÇANKIRI') !== false || stripos($distName, 'KARABUK') !== false) {
                    continue;
                }
                $distName = trim(preg_replace('/\s*\/\s*KASTAMONU\s*/ui', '', $distName));

                // Address
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mAddr)) {
                    $address = trim($mAddr[1]);
                }

                // Phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardBody, $mPhone)) {
                    $phone = preg_replace('/[^\d]/', '', $mPhone[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mPhone2)) {
                    $phone = preg_replace('/[^\d]/', '', $mPhone2[1]);
                }

                // Duty hours
                $hours = '';
                if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*<strong[^>]*>(.*?)<\/strong>/is', $cardBody, $mHours)) {
                    $hours = trim(strip_tags($mHours[1]));
                }

                // Map & coords
                $maps = null;
                $latitude = null;
                $longitude = null;
                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $cardBody, $mMaps)) {
                    $maps = html_entity_decode($mMaps[1]);
                    if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $mCoords)) {
                        $latitude = (float) $mCoords[1];
                        $longitude = (float) $mCoords[2];
                    }
                }

                $p = [
                    'name'       => $name,
                    'district'   => $distName,
                    'address'    => $address,
                    'phone'      => $phone,
                ];

                if (!empty($hours)) {
                    $p['duty_hours'] = $hours;
                }

                if (!empty($maps)) {
                    $p['maps_url'] = $maps;
                }
                if ($latitude !== null && $longitude !== null) {
                    $p['latitude'] = $latitude;
                    $p['longitude'] = $longitude;
                }

                $pharmacies[] = $p;
            }
        }

        return $pharmacies;
    }
}
