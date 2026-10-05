<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class DuzceScraper extends BaseScraper
{
    protected string $cityName = 'Düzce';
    protected int $plateCode = 81;
    protected string $primaryUrl = 'https://www.duzceeo.org/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobet-kart[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $cards)) {
            foreach ($cards[1] as $cardBody) {
                $name = '';
                if (preg_match('/<h4[^>]*class=["\']red border["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/h4>/is', $cardBody, $mH4)) {
                    $name = trim(strip_tags($mH4[1]));
                } elseif (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $cardBody, $mH)) {
                    $name = trim(strip_tags($mH[1]));
                }

                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                if (empty($name) || $this->isBlacklistedTitle($name)) {
                    continue;
                }

                $distName = '';
                if (preg_match('/<i class=[\'"]fa fa-arrow-right[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mDist)) {
                    $distRaw = trim(strip_tags($mDist[1]));
                    $distRaw = preg_replace('/^MERKEZ\s*-\s*DÜZCE\s*MERKEZ/ui', 'Merkez', $distRaw);
                    $distRaw = preg_replace('/^DÜZCE\s*-\s*/ui', '', $distRaw);
                    $distName = Str::titleTr(trim($distRaw));
                }

                // Address
                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mAddr)) {
                    $address = trim($mAddr[1]);
                }

                // Phone
                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardBody, $mPhone)) {
                    $phone = trim($mPhone[1]);
                }

                // Hours
                $hours = '';
                if (preg_match('/<i class=["\']fa fa-clock-o[^\'"]*["\']><\/i>\s*<span[^>]*>(.*?)<\/span>/is', $cardBody, $mHours)) {
                    $hours = trim(strip_tags($mHours[1]));
                }

                // Maps & coords
                $maps = null;
                $lat = null;
                $lng = null;
                if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $cardBody, $mMaps)) {
                    $maps = html_entity_decode($mMaps[1]);
                    if (preg_match('/q=([0-9.]+)(?:,|%2C|\+)+([0-9.]+)/i', $maps, $mCoords)) {
                        $lat = (float) $mCoords[1];
                        $lng = (float) $mCoords[2];
                    }
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $distName,
                    'address'    => $address,
                    'phone'      => $phone,
                    'duty_hours' => $hours,
                    'latitude'   => $lat,
                    'longitude'  => $lng,
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
