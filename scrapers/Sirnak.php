<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class SirnakScraper extends BaseScraper
{
    protected string $cityName = 'Şırnak';
    protected int $plateCode = 73;
    protected string $primaryUrl = 'https://sirnakeo.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobetci12[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $cards)) {
            foreach ($cards[1] as $cardBody) {
                $name = '';
                if (preg_match('/<h4[^>]*class=["\']text-danger["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/h4>/is', $cardBody, $mH4)) {
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
                    $distRaw = preg_replace('/^ŞIRNAK\s*/ui', '', $distRaw);
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
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'map_url'    => $maps,
                    'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
                ]);
            }
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
