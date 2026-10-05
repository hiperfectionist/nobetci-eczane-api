<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class CanakkaleScraper extends BaseScraper
{
    protected string $cityName = 'Çanakkale';
    protected int $plateCode = 17;
    protected string $primaryUrl = 'https://www.canakkaleeo.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://canakkaleeo.org.tr/nobetci-eczaneler',
        'https://www.canakkaleeo.org.tr/'
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

        // Split by district headers: <h3 class="main-color">(.*?)</h3>
        $sections = preg_split('/<h3[^>]*class=["\']main-color["\'][^>]*>(.*?)<\/h3>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($sections) > 1) {
            for ($i = 1; $i < count($sections); $i += 2) {
                $rawHeader = trim(strip_tags($sections[$i]));
                $sectionHtml = $sections[$i + 1] ?? '';

                // Skip generic header like "Bugün Nöbetçi Eczaneler"
                if (stripos($rawHeader, 'Bugün') !== false) {
                    continue;
                }

                // Clean district name
                $distName = trim(preg_replace('/(?:\s+NÖBETÇİ|\s+NÖBETCI|\s+ECZANELER|\s+ECZANELERİ|\s+ECZANESİ).*/ui', '', $rawHeader));

                if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobetci[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $sectionHtml, $cards)) {
                    foreach ($cards[1] as $cardBody) {
                        // Title
                        $name = '';
                        if (preg_match('/<h4[^>]*class=["\']tred["\'][^>]*>(.*?)<\/h4>/is', $cardBody, $mName)) {
                            $name = trim(strip_tags($mName[1]));
                        } elseif (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $cardBody, $mName2)) {
                            $name = trim(strip_tags($mName2[1]));
                        }

                        if (empty($name) || $this->isBlacklistedTitle($name)) {
                            continue;
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
                        } elseif (preg_match('/<i class=[\'"]fa fa-phone[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mPhone2)) {
                            $phone = trim(strip_tags($mPhone2[1]));
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
            }
        }

        // Fallback: TEB cards
        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
