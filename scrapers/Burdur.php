<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class BurdurScraper extends BaseScraper
{
    protected string $cityName = 'Burdur';
    protected int $plateCode = 15;
    protected string $primaryUrl = 'https://www.burdureo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://burdureo.org.tr/nobetci-eczaneler',
        'https://www.burdureo.org.tr/',
        'https://burdureo.org.tr/'
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

        // Pattern matching the Burdur chamber cards
        $pattern = '/<font[^>]*class=["\']color["\'][^>]*><strong><i class=["\']icon-user-md["\']><\/i>\s*([^<]+)<\/strong><\/font>(.*?)(?=<font[^>]*class=["\']color["\']|<div class=["\']footer|$)/is';
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $idx => $rawName) {
                $name = trim(strip_tags($rawName));
                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                $body = $matches[2][$idx];

                // District: icon-hand-right
                $dist = '';
                if (preg_match('/<i class=["\']icon-hand-right["\']><\/i>\s*([^<]+)/i', $body, $dMatch)) {
                    $dist = trim($dMatch[1]);
                }

                // Address: icon-home
                $address = '';
                if (preg_match('/<i class=["\']icon-home["\']><\/i>\s*([^<]+)/i', $body, $aMatch)) {
                    $address = trim($aMatch[1]);
                }

                // Phone: tel: link or icon-phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $pMatch)) {
                    $phone = trim($pMatch[1]);
                } elseif (preg_match('/<i class=["\']icon-phone["\']><\/i>\s*([^<]+)/i', $body, $pMatch2)) {
                    $phone = trim(strip_tags($pMatch2[1]));
                }

                // Maps URL & Coords
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
                    'name'      => $name,
                    'district'  => $dist,
                    'address'   => $address,
                    'phone'     => $phone,
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'map_url'   => $maps
                ]);
            }
        }

        // Fallback: standard TEB cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
