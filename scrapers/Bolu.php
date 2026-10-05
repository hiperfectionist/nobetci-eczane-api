<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class BoluScraper extends BaseScraper
{
    protected string $cityName = 'Bolu';
    protected int $plateCode = 14;
    protected string $primaryUrl = 'https://seobit.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://www.seobit.org.tr/nobetci-eczaneler',
        'https://seobit.org.tr/'
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

        // Method 1: Dedicated nobetci-eczaneler cards (<h4 class="red border">)
        if (preg_match_all('/<h4[^>]*class=["\'][^"\']*red border[^"\']*["\'][^>]*>(.*?)<\/h4>(.*?)(?=<h4[^>]*class=["\']red border|<div class="footer|$)/is', $html, $cards)) {
            foreach ($cards[1] as $idx => $rawName) {
                $name = trim(strip_tags($rawName));
                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                $body = $cards[2][$idx];

                // District: after fa-arrow-right
                $dist = '';
                if (preg_match('/<i class=[\'"]fa fa-arrow-right[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $dMatch)) {
                    $dist = trim($dMatch[1]);
                }

                // Address: after fa-home
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $aMatch)) {
                    $address = trim($aMatch[1]);
                }

                // Phone: href="tel:..."
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $pMatch)) {
                    $phone = trim($pMatch[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $body, $pMatch2)) {
                    $phone = trim(strip_tags($pMatch2[1]));
                }

                // Map & coords
                $maps = null;
                $latitude = null;
                $longitude = null;
                if (preg_match('/href=["\'](https?:\/\/(?:www\.)?google\.com\/maps[^\'"]+)[\'"]/i', $body, $mMatch)) {
                    $maps = html_entity_decode($mMatch[1]);
                    if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $cMatch)) {
                        $latitude = (float) $cMatch[1];
                        $longitude = (float) $cMatch[2];
                    }
                }

                // Duty hours
                $hours = '';
                if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*<span[^>]*>(.*?)<\/span>/is', $body, $hMatch)) {
                    $hours = trim(strip_tags($hMatch[1]));
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $dist,
                    'address'    => $address,
                    'phone'      => $phone,
                    'duty_hours' => $hours,
                    'latitude'   => $latitude,
                    'longitude'  => $longitude,
                    'map_url'    => $maps
                ]);
            }
        }

        // Method 2 (Fallback): Homepage district tabs and text-blue cards
        if (empty($pharmacies) && preg_match_all('/<a\s+[^>]*class=["\'][^"\']*list-group-item active[^"\']*["\'][^>]*><strong>(.*?)<\/strong><\/a>/i', $html, $dMatches, PREG_OFFSET_CAPTURE)) {
            for ($i = 0; $i < count($dMatches[0]); $i++) {
                $curDistrict = trim(strip_tags($dMatches[1][$i][0]));
                $startPos = $dMatches[0][$i][1];
                $endPos = isset($dMatches[0][$i + 1]) ? $dMatches[0][$i + 1][1] : strlen($html);
                $sectionHtml = substr($html, $startPos, $endPos - $startPos);

                $sidebarPos = strpos($sectionHtml, 'nobetciEczaneler');
                if ($sidebarPos !== false) {
                    $sectionHtml = substr($sectionHtml, 0, $sidebarPos);
                }

                if (preg_match_all('/<h4[^>]*class=["\'][^"\']*text-blue[^"\']*["\'][^>]*>(.*?)<\/h4>(.*?)(?=<h4|<div\s+class=["\']col-md-|$)/is', $sectionHtml, $cards)) {
                    foreach ($cards[1] as $cIdx => $rawName) {
                        $name = trim(strip_tags($rawName));
                        if (empty($name) || $this->isBlacklistedTitle($name)) {
                            continue;
                        }

                        $cardBody = $cards[2][$cIdx];

                        $address = '';
                        if (preg_match('/<i class=[\'"]fa fa-home[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mAddr)) {
                            $address = trim($mAddr[1]);
                        }

                        $phone = '';
                        if (preg_match('/href=[\'"]tel:([^[\'"]+)[\'"]/i', $cardBody, $mPhone)) {
                            $phone = trim($mPhone[1]);
                        } elseif (preg_match('/<i class=[\'"]fa fa-phone[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mPhone2)) {
                            $phone = trim(strip_tags($mPhone2[1]));
                        }

                        $maps = null;
                        $latitude = null;
                        $longitude = null;
                        if (preg_match('/href=["\'](https?:\/\/(?:www\.)?google\.com\/maps[^\'"]+)[\'"]/i', $cardBody, $mMaps)) {
                            $maps = html_entity_decode($mMaps[1]);
                            if (preg_match('/q=([0-9.]+),([0-9.]+)/i', $maps, $mCoords)) {
                                $latitude = (float) $mCoords[1];
                                $longitude = (float) $mCoords[2];
                            }
                        }

                        $pharmacies[] = $this->createPharmacy([
                            'name'      => $name,
                            'district'  => $curDistrict,
                            'address'   => $address,
                            'phone'     => $phone,
                            'latitude'  => $latitude,
                            'longitude' => $longitude,
                            'map_url'   => $maps
                        ]);
                    }
                }
            }
        }

        // Method 3 (Fallback): Standard TEB cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
