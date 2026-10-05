<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class DiyarbakirScraper extends BaseScraper
{
    protected string $cityName = 'Diyarbakır';
    protected int $plateCode = 21;
    protected string $primaryUrl = 'https://www.diyarbakireo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://diyarbakireo.org.tr/nobetci-eczaneler',
        'https://www.diyarbakireo.org.tr/'
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

        // Split by district section headers (<h3 class="main-color">...</h3>)
        $sections = preg_split('/<h3[^>]*class=["\']main-color["\'][^>]*>(.*?)<\/h3>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($sections) > 1) {
            for ($i = 1; $i < count($sections); $i += 2) {
                $secTitle = trim(strip_tags($sections[$i]));
                $secHtml = $sections[$i + 1] ?? '';

                if (stripos($secTitle, 'Bugün') !== false) {
                    continue;
                }

                $mainDistrict = trim(preg_replace('/(?:\s+NÖBETÇİ|\s+NÖBETCI|\s+ECZANELER|\s+ECZANELERİ|\s+ECZANESİ).*/ui', '', $secTitle));

                if (preg_match_all('/<h3[^>]*class=["\'][^"\']*text-danger[^"\']*["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>(?:\s*-\s*([^<]+))?<\/h3>(.*?)(?=<h3[^>]*class=["\']text-danger|<div class="footer|$)/is', $secHtml, $cards)) {
                    foreach ($cards[1] as $cIdx => $rawName) {
                        $name = trim(strip_tags($rawName));
                        if (empty($name) || $this->isBlacklistedTitle($name)) {
                            continue;
                        }

                        $subArea = trim(strip_tags($cards[2][$cIdx] ?? ''));
                        $body = $cards[3][$cIdx];

                        // Address
                        $address = '';
                        if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $mAddr)) {
                            $address = trim($mAddr[1]);
                        }

                        // Phone
                        $phone = '';
                        if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $mPhone)) {
                            $phone = trim($mPhone[1]);
                        } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $mPhone2)) {
                            $phone = trim(strip_tags($mPhone2[1]));
                        }

                        // Map & coords
                        $maps = null;
                        $latitude = null;
                        $longitude = null;
                        if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $body, $mMaps)) {
                            $maps = html_entity_decode($mMaps[1]);
                            if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $mCoords)) {
                                $latitude = (float) $mCoords[1];
                                $longitude = (float) $mCoords[2];
                            }
                        }

                        $directions = ($subArea && strcasecmp($subArea, $mainDistrict) !== 0) ? $subArea : '';

                        $pharmacies[] = $this->createPharmacy([
                            'name'       => $name,
                            'district'   => $mainDistrict,
                            'address'    => $address,
                            'directions' => $directions,
                            'phone'      => $phone,
                            'latitude'   => $latitude,
                            'longitude'  => $longitude,
                            'map_url'    => $maps
                        ]);
                    }
                }
            }
        }

        // Fallback: standard cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
