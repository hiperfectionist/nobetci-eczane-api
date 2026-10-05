<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class IgdirScraper extends BaseScraper
{
    protected string $cityName = 'Iğdır';
    protected int $plateCode = 76;
    protected string $primaryUrl = 'https://www.erzurumeo.org.tr/nobetci-eczaneler/76';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

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

                $distName = Str::titleTr(trim(preg_replace('/\s*\/\s*IĞDIR\s*/ui', '', $distName)));

                // Address
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mAddr)) {
                    $address = trim($mAddr[1]);
                }

                // Phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardBody, $mPhone)) {
                    $phone = trim($mPhone[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mPhone2)) {
                    $phone = trim(strip_tags($mPhone2[1]));
                }

                // Duty hours
                $hours = '';
                if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*<strong[^>]*>(.*?)<\/strong>/is', $cardBody, $mHours)) {
                    $hours = trim(strip_tags($mHours[1]));
                }

                // Maps & coords
                $maps = null;
                $latitude = null;
                $longitude = null;
                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $cardBody, $mMaps)) {
                    $maps = html_entity_decode($mMaps[1]);
                    if (preg_match('/q=([0-9.]+)(?:,|%2C|\+)+([0-9.]+)/i', $maps, $mCoords)) {
                        $latitude = (float) $mCoords[1];
                        $longitude = (float) $mCoords[2];
                    }
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $distName,
                    'address'    => $address,
                    'phone'      => $phone,
                    'duty_hours' => $hours,
                    'latitude'   => $latitude,
                    'longitude'  => $longitude,
                    'map_url'    => $maps
                ]);
            }
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
