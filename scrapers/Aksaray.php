<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class AksarayScraper extends BaseScraper
{
    protected string $cityName = 'Aksaray';
    protected int $plateCode = 68;
    protected string $primaryUrl = 'https://www.aksarayeo.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        // Strictly isolate Aksaray section, cutting before Kırşehir section
        $startPos = mb_stripos($html, 'MERKEZ/AKSARAY BUGÜN NÖBETÇİ ECZANELER');
        if ($startPos !== false) {
            $h5Pos = mb_strrpos(mb_substr($html, 0, $startPos), '<h5');
            if ($h5Pos !== false) {
                $startPos = $h5Pos;
            }
            $endPos = mb_stripos($html, 'KIRŞEHİR BUGÜN NÖBETÇİ ECZANELER', $startPos);
            $html = ($endPos !== false) ? mb_substr($html, $startPos, $endPos - $startPos) : mb_substr($html, $startPos);
        }

        $pharmacies = [];

        // Split by district headers: <h5 class="title pagetitle"><strong>{DISTRICT} BUGÜN NÖBETÇİ ECZANELER</strong></h5>
        $sections = preg_split('/<h5[^>]*class=["\'][^"\']*pagetitle[^"\']*["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/h5>/isu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($sections); $i += 2) {
            $distHeader = trim(strip_tags($sections[$i]));
            $distName = preg_replace('/\s*(?:BUGÜN\s+NÖBETÇİ\s+ECZANELER|NÖBETÇİ\s+ECZANELER).*$/ui', '', $distHeader);
            $distName = trim(preg_replace('/\s*\/\s*AKSARAY\s*/ui', '', $distName));
            $distName = Str::titleTr(trim($distName));
            $content = $sections[$i + 1] ?? '';

            if (preg_match_all('/<div[^>]*class=["\'][^"\']*nobet-kart[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $content, $cards)) {
                foreach ($cards[1] as $cardBody) {
                    $name = '';
                    if (preg_match('/<h5[^>]*class=["\']red border["\'][^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/h5>/is', $cardBody, $mName)) {
                        $name = trim(strip_tags($mName[1]));
                    } elseif (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $cardBody, $mH)) {
                        $name = trim(strip_tags($mH[1]));
                    }

                    $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                    if (empty($name) || $this->isBlacklistedTitle($name)) {
                        continue;
                    }

                    $address = '';
                    if (preg_match('/<i class=[\'"]fa fa-home[^\'"]*[\'"]><\/i>\s*([^<]+)/i', $cardBody, $mAddr)) {
                        $address = trim($mAddr[1]);
                    }

                    $phone = '';
                    if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardBody, $mPhone)) {
                        $phone = trim($mPhone[1]);
                    }

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
        }

        if (empty($pharmacies)) {
            $pharmacies = $this->parseTebCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
