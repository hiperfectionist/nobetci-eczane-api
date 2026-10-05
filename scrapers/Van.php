<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class VanScraper extends BaseScraper
{
    protected string $cityName = 'Van';
    protected int $plateCode = 65;
    protected string $primaryUrl = 'https://www.vaneczaciodasi.org.tr/nobetci-eczaneler';
    protected array $fallbackUrls = [
        'https://vaneczaciodasi.org.tr/nobetci-eczaneler'
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

        $pharmacies = $this->parseCards($html);

        return $this->filterPharmacies($pharmacies, $district);
    }

    private function parseCards(string $html): array
    {
        // 1. Isolate the Van section only (ignore Bitlis and Hakkari)
        $start = mb_stripos($html, "Van'da Bugün Nöbetçi Eczaneler");
        if ($start === false) {
            $start = mb_stripos($html, "Van'da");
        }

        $end = mb_stripos($html, "Bitlis'de Bugün Nöbetçi Eczaneler");
        if ($end === false) {
            $end = mb_stripos($html, "Hakkari'de Bugün Nöbetçi Eczaneler");
        }

        if ($start !== false) {
            if ($end !== false && $end > $start) {
                $section = mb_substr($html, $start, $end - $start);
            } else {
                $section = mb_substr($html, $start);
            }
        } else {
            $section = $html;
        }

        $parts = preg_split('/<div class="col-md-12 nobetci">/i', $section);
        if (count($parts) > 1) {
            array_shift($parts);
        } else {
            return [];
        }

        $pharmacies = [];

        foreach ($parts as $p) {
            // 1. Name & District: <h4 class="tred text-center"><strong>ERDOĞAN  ECZANESİ<br><small>İPEKYOLU</small></strong></h4>
            $name = '';
            $district = '';
            if (preg_match('/<h4[^>]*>(.*?)<\/h4>/is', $p, $h4)) {
                $titleHtml = $h4[1];
                if (preg_match('/<small[^>]*>(.*?)<\/small>/is', $titleHtml, $sm)) {
                    $district = trim(strip_tags($sm[1]));
                }
                $name = trim(strip_tags(preg_replace('/<small[^>]*>.*?<\/small>/is', '', $titleHtml)));
            }
            $cleanName = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);

            // 2. Phone
            $phone = '';
            if (preg_match('/<a[^>]*href=["\']tel:([^"\']+)["\']/is', $p, $ph)) {
                $phone = preg_replace('/[^\d]/', '', $ph[1]);
            }

            // 3. Map & Coords
            $mapUrl = '';
            $lat = null;
            $lng = null;
            if (preg_match('/<a[^>]*href=["\'](https?:\/\/(?:maps\.google\.com|www\.google\.com\/maps)[^"\']+)["\']/is', $p, $mapM)) {
                $mapUrl = html_entity_decode(trim($mapM[1]));
                if (preg_match('/q=([0-9.-]+)(?:,|%2C|\+)+([0-9.-]+)/i', $mapUrl, $coordM)) {
                    $lat = (float)$coordM[1];
                    $lng = (float)$coordM[2];
                }
            }

            // 4. Address: between fa-home and <br> or fa-phone
            $address = '';
            if (preg_match("/<i[^>]*class=['\"][^'\"]*fa-home[^'\"]*['\"][^>]*><\/i>(.*?)(?:<br\s*\/?>\s*<i\s+class=['\"][^'\"]*fa-phone|<a\s+href=['\"]tel:)/is", $p, $addrM)) {
                $address = trim(strip_tags(str_replace('<br>', ' ', $addrM[1])));
            } elseif (preg_match("/<i[^>]*class=['\"][^'\"]*fa-home[^'\"]*['\"][^>]*><\/i>(.*?)<br/is", $p, $addrM2)) {
                $address = trim(strip_tags($addrM2[1]));
            }

            if (!empty($cleanName)) {
                $item = [
                    'name'     => $cleanName,
                    'district' => Str::titleTr($district ?: 'İpekyolu'),
                    'address'  => $address,
                    'phone'    => $phone,
                ];

                if (!empty($mapUrl)) {
                    $item['maps_url'] = $mapUrl;
                }
                if ($lat !== null && $lng !== null) {
                    $item['latitude'] = $lat;
                    $item['longitude'] = $lng;
                }

                $pharmacies[] = $item;
            }
        }

        return $pharmacies;
    }
}
