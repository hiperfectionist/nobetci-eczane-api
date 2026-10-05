<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class TrabzonScraper extends BaseScraper
{
    protected string $cityName = 'Trabzon';
    protected int $plateCode = 61;
    protected string $primaryUrl = 'https://www.trabzoneczaciodasi.org.tr/nobetci-eczaneler/61';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = $this->parseTrabzonCards($html);
        return $this->filterPharmacies($pharmacies, $district);
    }

    public function parseTrabzonCards(string $html): array
    {
        $pharmacies = [];

        preg_match_all('/<div[^>]*class=["\'][^"\']*standart-post[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/isu', $html, $matches);

        if (empty($matches[1])) {
            return $this->parseHeuristicCards($html, null);
        }

        foreach ($matches[1] as $cardHtml) {
            // Pharmacy Name
            if (!preg_match('/<h[1-2][^>]*class=["\'][^"\']*red[^"\']*["\'][^>]*><strong>\s*(.*?)\s*<\/strong><\/h[1-2]>/isu', $cardHtml, $nameMatch)) {
                continue;
            }
            $name = Str::clean(strip_tags($nameMatch[1]));

            // District
            $dist = '';
            if (preg_match('/<a[^>]*class=["\'][^"\']*category[^"\']*["\'][^>]*>\s*(.*?)\s*<\/a>/isu', $cardHtml, $dMatch)) {
                $dist = Str::titleTr(trim(strip_tags($dMatch[1])));
            }

            // Duty hours
            $hours = '';
            if (preg_match('/<i class="fa fa-calendar"><\/i>\s*([^<]+)/iu', $cardHtml, $hMatch)) {
                $hours = Str::clean($hMatch[1]);
            }

            // Address
            $address = '';
            if (preg_match('/<i class="fa fa-home"><\/i>\s*([^<]+)/iu', $cardHtml, $aMatch)) {
                $address = Str::clean($aMatch[1]);
            }

            // Phone
            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardHtml, $tel)) {
                $phone = $tel[1];
            }

            // Map URL
            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google)[^"\']+)["\']/i', $cardHtml, $map)) {
                $mapUrl = $map[1];
            }

            $coords = $this->extractCoordinates($mapUrl);

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => $hours,
                'map_url'    => $mapUrl,
                'latitude'   => $coords['lat'],
                'longitude'  => $coords['lng']
            ]);
        }

        return $pharmacies;
    }
}
