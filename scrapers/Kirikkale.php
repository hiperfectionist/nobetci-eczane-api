<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KirikkaleScraper extends BaseScraper
{
    protected string $cityName = 'Kırıkkale';
    protected int $plateCode = 71;
    protected string $primaryUrl = 'https://www.eczaneler.gen.tr/iframe.php?lokasyon=71';
    protected string $fallbackUrl = 'https://eczaneler.org/kirikkale-nobetci-eczaneleri';

    public function scrape(?string $district = null): array
    {
        $pharmacies = [];

        // 1. Try primary source (eczaneler.gen.tr iframe)
        $html = $this->fetchHtml($this->primaryUrl);
        if (!empty($html) && stripos($html, 'Just a moment') === false && stripos($html, 'bottomborder') !== false) {
            $pharmacies = $this->parseIframeTable($html);
        }

        // 2. Fallback to eczaneler.org if primary was blocked or empty
        if (empty($pharmacies)) {
            $fbHtml = $this->fetchHtml($this->fallbackUrl);
            if (!empty($fbHtml)) {
                $pharmacies = $this->parseEczanelerOrg($fbHtml);
            }
        }

        return $this->filterPharmacies($pharmacies, $district);
    }

    protected function parseIframeTable(string $html): array
    {
        $pharmacies = [];
        $pattern = '/<tr>\s*<td><img[^>]*src=[\'"][^\'"]*eczane-sm\.jpg[\'"][^>]*><\/td>\s*<td><span[^>]*class=[\'"]text-dark[\'"]><b>(.*?)<\/b><\/span>(?:.*?<a\s+href=[\'"]([^\'"]*maps[^\'"]*)[\'"])?.*?<span\s+class=[\'"]text-secondary[\'"]>\((.*?)\)<\/span><\/td><\/tr>\s*<tr>\s*<td><img[^>]*src=[\'"][^\'"]*telefon\.png[\'"][^>]*><\/td>\s*<td>(.*?)<\/td>.*?<tr\s+class=[\'"]bottomborder[\'"]>\s*<td[^>]*><img[^>]*src=[\'"][^\'"]*adres\.png[\'"][^>]*><\/td>\s*<td>(.*?)<\/td><\/tr>/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(strip_tags($m[1]));
                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);

                $mapUrl = isset($m[2]) ? html_entity_decode($m[2]) : '';
                $distName = Str::titleTr(trim(strip_tags($m[3])));
                $phone = trim(preg_replace('/[^\d]/', '', $m[4]));

                $addrRaw = $m[5];
                $directions = '';
                if (preg_match('/<span\s+class=[\'"]text-muted[\'"]>\((.*?)\)<\/span>/is', $addrRaw, $mDir)) {
                    $directions = trim(strip_tags($mDir[1]));
                    $addrRaw = str_replace($mDir[0], '', $addrRaw);
                }
                $address = trim(strip_tags(str_replace('<br>', ' ', $addrRaw)));

                $lat = null;
                $lng = null;
                if (!empty($mapUrl) && preg_match('/daddr=([0-9.]+)(?:,|%2C|\+)+([0-9.]+)/i', $mapUrl, $mCoords)) {
                    $lat = (float) $mCoords[1];
                    $lng = (float) $mCoords[2];
                }

                $pharmacies[] = $this->createPharmacy([
                    'name'       => $name,
                    'district'   => $distName,
                    'address'    => $address,
                    'phone'      => $phone,
                    'directions' => $directions,
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'map_url'    => $mapUrl,
                    'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
                ]);
            }
        }

        return $pharmacies;
    }

    protected function parseEczanelerOrg(string $html): array
    {
        $pharmacies = [];

        // Try JSON-LD ItemList
        if (preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $mJson)) {
            foreach ($mJson[1] as $jStr) {
                $data = json_decode($jStr, true);
                if (isset($data['@graph'])) {
                    foreach ($data['@graph'] as $node) {
                        if (isset($node['@type']) && $node['@type'] === 'ItemList' && isset($node['itemListElement'])) {
                            foreach ($node['itemListElement'] as $item) {
                                $p = $item['item'] ?? [];
                                $name = trim($p['name'] ?? '');
                                $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
                                if (empty($name)) continue;

                                $dist = Str::titleTr(trim($p['address']['addressLocality'] ?? ''));
                                $addr = trim($p['address']['streetAddress'] ?? '');
                                $tel  = trim($p['telephone'] ?? '');
                                $lat  = isset($p['geo']['latitude']) ? (float)$p['geo']['latitude'] : null;
                                $lng  = isset($p['geo']['longitude']) ? (float)$p['geo']['longitude'] : null;
                                $map  = ($lat !== null && $lng !== null) ? "https://www.google.com/maps?q={$lat},{$lng}" : '';

                                $pharmacies[] = $this->createPharmacy([
                                    'name'       => $name,
                                    'district'   => $dist,
                                    'address'    => $addr,
                                    'phone'      => $tel,
                                    'latitude'   => $lat,
                                    'longitude'  => $lng,
                                    'map_url'    => $map,
                                    'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
                                ]);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($pharmacies)) {
            return $pharmacies;
        }

        // HTML Card fallback
        preg_match_all('/href=["\'](\/kirikkale-([a-z0-9]+)-[a-z0-9-]+-eczanesi)["\'][^>]*>([^<]+)<\/a>(.*?)(?=<a[^>]*href=["\']\/kirikkale-|\z)/isu', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $name = trim(strip_tags($m[3]));
            $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            if (empty($name)) continue;

            $distSlug = $m[2];
            $body = $m[4];

            $district = Str::titleTr($distSlug);
            $address = '';
            if (preg_match('/<p[^>]*class=["\'][^"\']*line-clamp-2[^"\']*["\'][^>]*>(.*?)<\/p>/isu', $body, $mAddr)) {
                $address = trim(strip_tags($mAddr[1]));
            }

            $directions = '';
            if (preg_match('/Tarif:<\/span>\s*<span[^>]*>(.*?)<\/span>/isu', $body, $mDir)) {
                $directions = trim(strip_tags($mDir[1]));
            }

            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $mTel)) {
                $phone = trim($mTel[1]);
            }

            $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($name . ' Eczanesi ' . $district . ' ' . $this->cityName);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $district,
                'address'    => $address,
                'directions' => $directions,
                'phone'      => $phone,
                'map_url'    => $mapUrl,
                'duty_hours' => 'Bugün 18:00 - Ertesi gün 08:30'
            ]);
        }

        return $pharmacies;
    }
}
