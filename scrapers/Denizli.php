<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class DenizliScraper extends BaseScraper
{
    protected string $cityName = 'Denizli';
    protected int $plateCode = 20;
    protected string $primaryUrl = 'https://denizlieczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.denizlieczaciodasi.org.tr/nobetci-eczaneler',
        'https://denizlieczaciodasi.org.tr/'
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

        // Parse Denizli chamber cards (<h4 class="red border">)
        if (preg_match_all('/<h4[^>]*class=["\'][^"\']*red border[^"\']*["\'][^>]*>(.*?)<\/h4>(.*?)(?=<h4[^>]*class=["\']red border|<div class="footer|$)/is', $html, $cards)) {
            foreach ($cards[1] as $idx => $rawName) {
                $name = trim(strip_tags($rawName));
                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                $body = $cards[2][$idx];

                // District
                $dist = '';
                if (preg_match('/<i class=[\'"]fa fa-arrow-right[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $dMatch)) {
                    $dist = trim($dMatch[1]);
                }

                // Address
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $aMatch)) {
                    $address = trim($aMatch[1]);
                }

                // Directions
                $dir = '';
                if (preg_match('/<i class=[\'"]fa fa-arrows[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $dirMatch)) {
                    $dir = trim($dirMatch[1]);
                }

                // Phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $pMatch)) {
                    $phone = trim($pMatch[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $pMatch2)) {
                    $phone = trim(strip_tags($pMatch2[1]));
                }

                // Duty hours
                $hours = '';
                if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*<span[^>]*>(.*?)<\/span>/is', $body, $hMatch)) {
                    $hours = trim(strip_tags($hMatch[1]));
                }

                // Map & coords
                $maps = null;
                $latitude = null;
                $longitude = null;
                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $body, $mMatch)) {
                    $maps = html_entity_decode($mMatch[1]);
                    if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $cMatch)) {
                        $latitude = (float) $cMatch[1];
                        $longitude = (float) $cMatch[2];
                    }
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $dist,
                    'address'    => $address,
                    'directions' => $dir,
                    'phone'      => $phone,
                    'duty_hours' => $hours,
                    'latitude'   => $latitude,
                    'longitude'  => $longitude,
                    'map_url'    => $maps
                ]);
            }
        }

        // Fallback: standard TEB cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
