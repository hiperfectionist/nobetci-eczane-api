<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class EdirneScraper extends BaseScraper
{
    protected string $cityName = 'Edirne';
    protected int $plateCode = 22;
    protected string $primaryUrl = 'https://www.edirneeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://edirneeo.org.tr/nobetci-eczaneler',
        'https://www.edirneeo.org.tr/'
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

        // Parse Edirne chamber cards (<div class="nobetci">)
        if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobetci[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $cards)) {
            foreach ($cards[1] as $card) {
                $name = '';
                $distName = '';
                if (preg_match('/<h4[^>]*class=["\']tred["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>(?:\s*-\s*([^<]+))?<\/h4>/is', $card, $mH4)) {
                    $name = trim(strip_tags($mH4[1]));
                    $distName = isset($mH4[2]) ? trim(strip_tags($mH4[2])) : '';
                } elseif (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $card, $mH)) {
                    $name = trim(strip_tags($mH[1]));
                }

                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                // Address
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $card, $mAddr)) {
                    $address = trim($mAddr[1]);
                }

                // Phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $card, $mPhone)) {
                    $phone = trim($mPhone[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $card, $mPhone2)) {
                    $phone = trim(strip_tags($mPhone2[1]));
                }

                // Maps & coords
                $maps = null;
                $latitude = null;
                $longitude = null;
                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $card, $mMaps)) {
                    $maps = html_entity_decode($mMaps[1]);
                    if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $mCoords)) {
                        $latitude = (float) $mCoords[1];
                        $longitude = (float) $mCoords[2];
                    }
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'      => $name,
                    'district'  => $distName,
                    'address'   => $address,
                    'phone'     => $phone,
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'map_url'   => $maps
                ]);
            }
        }

        // Fallback: standard cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
