<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class MardinScraper extends BaseScraper
{
    protected string $cityName = 'Mardin';
    protected int $plateCode = 47;
    protected string $primaryUrl = 'https://www.mardineczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://mardineczaciodasi.org.tr/nobetci-eczaneler',
        'https://www.mardineo.org.tr/nobetci-eczaneler'
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

        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-9');
        }

        $pharmacies = $this->parseMardinCards($html);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseMardinCards(string $html): array
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

                $sirnakDistricts = ['SIRNAK', 'BEYTUSSEBAP', 'CIZRE', 'IDIL', 'SILOPI', 'ULUDERE', 'GUCLUKONAK'];
                $distSlug = Str::slug($distName);
                $isSirnak = false;
                foreach ($sirnakDistricts as $sd) {
                    if (str_contains($distSlug, strtolower($sd))) {
                        $isSirnak = true;
                        break;
                    }
                }
                if ($isSirnak) {
                    continue;
                }

                $address = '';
                if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*(.*?)(?:<br\s*\/?>\s*<i class=[\'"]fa fa-phone|<\/p)/is', $cardBody, $mAddr)) {
                    $address = trim(strip_tags(str_replace('<br>', ' ', $mAddr[1])));
                }

                $phone = '';
                if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardBody, $mPhone)) {
                    $phone = preg_replace('/[^\d]/', '', $mPhone[1]);
                } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mPhone2)) {
                    $phone = preg_replace('/[^\d]/', '', $mPhone2[1]);
                }

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

                $pharmacies[] = [
                    'name'       => $name,
                    'district'   => $distName,
                    'address'    => $address,
                    'phone'      => $phone,
                    'latitude'   => $latitude,
                    'longitude'  => $longitude,
                    'maps_url'   => $maps
                ];
            }
        }

        return $pharmacies;
    }
}
