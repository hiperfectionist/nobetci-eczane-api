<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KayseriScraper extends BaseScraper
{
    protected string $cityName = 'Kayseri';
    protected int $plateCode = 38;
    protected string $primaryUrl = 'https://cbs.kayseri.bel.tr/nobetcieczaneler.aspx';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pharmacies = [];

        // Match table rows
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/isu', $html, $rows)) {
            foreach ($rows[1] as $rHtml) {
                if (preg_match_all('/<td[^>]*>(.*?)<\/td>/isu', $rHtml, $tds)) {
                    $cols = array_map(function ($val) {
                        return Str::clean(strip_tags($val));
                    }, $tds[1]);

                    // Looking for row with at least 5 columns: [Harita, Name, District, Neighborhood, Hours, Phone, Address]
                    if (count($cols) >= 6) {
                        $name = $cols[1];
                        if (empty($name) || Str::contains($name, 'Eczane Adı') || strlen($name) < 3) {
                            continue;
                        }

                        $dist = Str::titleTr($cols[2]);
                        $neighborhood = $cols[3] ?? '';
                        $hours = $cols[4] ?? '';
                        $phone = $cols[5] ?? '';
                        $address = $cols[6] ?? '';

                        if (!empty($neighborhood)) {
                            $address .= " ({$neighborhood} Mah.)";
                        }

                        $pharmacies[] = $this->createPharmacy([
                            'name'       => $name,
                            'district'   => $dist,
                            'address'    => $address,
                            'phone'      => $phone,
                            'duty_hours' => $hours
                        ]);
                    }
                }
            }
        }

        if (empty($pharmacies)) {
            return $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
