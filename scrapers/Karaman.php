<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KaramanScraper extends BaseScraper
{
    protected string $cityName = 'Karaman';
    protected int $plateCode = 70;
    protected string $primaryUrl = 'https://www.karamaneo.org.tr/nobet-listesi';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Split by district headers: <h2><span class="vatan_span">{DISTRICT} Nöbetçi Eczaneler
        $sections = preg_split('/<h2[^>]*>\s*<span[^>]*class=["\']vatan_span["\'][^>]*>(.*?)(?:<\/span>|<\/h2>)/isu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($sections); $i += 2) {
            $distHeader = trim(strip_tags($sections[$i]));
            // e.g. "Merkez Nöbetçi Eczaneler <br />06-10-2026"
            // The district is always the first word: Merkez, Ermenek, Ayrancı, Kazımkarabekir, Başyayla, Sarıveliler
            $parts = preg_split('/\s+/u', $distHeader);
            $distName = !empty($parts[0]) ? Str::titleTr(trim($parts[0])) : 'Merkez';
            $content = $sections[$i + 1] ?? '';

            if (preg_match_all('/<div[^>]*class=["\']row["\'][^>]*style=["\'][^"\']*margin-bottom:10px[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $content, $cards)) {
                foreach ($cards[1] as $cardBody) {
                    $name = '';
                    if (preg_match('/<h4[^>]*>(?:<i[^>]*><\/i>)?\s*([^<]+)<\/h4>/is', $cardBody, $mH4)) {
                        $name = trim(strip_tags($mH4[1]));
                    }

                    $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                    if (empty($name) || $this->isBlacklistedTitle($name)) {
                        continue;
                    }

                    $hours = '';
                    if (preg_match('/<i class=["\']icon-map-marker["\']><\/i>\s*<strong[^>]*>(.*?)<\/strong>/is', $cardBody, $mHours)) {
                        $hours = trim(strip_tags($mHours[1]));
                    }

                    $address = '';
                    if (preg_match('/<i class=["\']icon-home["\']><\/i>\s*([^<]+)/is', $cardBody, $mAddr)) {
                        $address = trim($mAddr[1]);
                    }

                    $phone = '';
                    if (preg_match('/<i class=["\']icon-phone["\']><\/i>\s*([^<-]+)/is', $cardBody, $mPhone)) {
                        $phone = trim($mPhone[1]);
                    }

                    $maps = null;
                    $lat = null;
                    $lng = null;
                    if (preg_match('/href=["\'](https?:\/\/[^"\']*maps[^"\']*)["\']/i', $cardBody, $mMaps)) {
                        $maps = html_entity_decode($mMaps[1]);
                        if (preg_match('/(?:ll|q)=([0-9.]+)(?:,|%2C|\+)+([0-9.]+)/i', $maps, $mCoords)) {
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
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
