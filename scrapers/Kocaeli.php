<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KocaeliScraper extends BaseScraper
{
    protected string $cityName = 'Kocaeli';
    protected int $plateCode = 41;
    protected string $primaryUrl = 'https://www.kocaeli.bel.tr/nobetci-eczaneler.html';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Match cards in Kocaeli municipality site
        // <a class="title" target="_blank" href="...">NAME</a>
        // <span class="long-url">...ADDRESS...</span>
        // <p class="sub_tags_inner">DISTRICT / NEIGHBORHOOD</p>
        // <p class="sub_tags_inner"><a href="tel:...">PHONE</a></p>
        preg_match_all('/<a[^>]*class=["\']title["\'][^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>\s*<span[^>]*class=["\']long-url["\'][^>]*>(.*?)<\/span>(.*?)(?=<a[^>]*class=["\']title|\z)/isu', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $mapUrl = Str::clean($m[1]);
            $name = Str::clean($m[2]);
            $address = Str::clean($m[3]);
            $rest = $m[4];

            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $rest, $tel)) {
                $phone = $tel[1];
            }

            $dist = '';
            if (preg_match('/<p[^>]*class=["\']sub_tags_inner["\'][^>]*>(.*?)<\/p>/isu', $rest, $pMatch)) {
                $distRaw = Str::clean(strip_tags($pMatch[1]));
                // Format: "İZMİT / KOZLUK"
                $parts = explode('/', $distRaw);
                $dist = Str::titleTr(trim($parts[0]));
            }

            $coords = $this->extractCoordinates($mapUrl);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $address,
                'phone'      => $phone,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng'],
                'duty_hours' => 'Bugün 18:30 - Yarın 08:30'
            ]);
        }

        if (empty($pharmacies)) {
            return $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
