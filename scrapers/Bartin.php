<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;
use DateTime;

class BartinScraper extends BaseScraper
{
    protected string $cityName = 'Bartın';
    protected int $plateCode = 74;
    protected string $primaryUrl = 'https://bartin.bel.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        $pattern = '/\[\s*"([^"]+)"\s*,\s*"([^"]+)"\s*,\s*"([^"]+)"\s*,\s*"([^"]+)"\s*,\s*"([^"]+)"\s*\]/';
        if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $now = time();
        $todayStr = date('d m Y');

        $activeNow = [];
        $todayUpcoming = [];

        foreach ($matches as $m) {
            $startStr = trim($m[1]);
            $endStr   = trim($m[2]);
            $name     = trim($m[3]);
            $phone    = trim($m[4]);
            $address  = trim($m[5]);

            $name = preg_replace('/(\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            if (empty($name)) continue;

            $startTs = DateTime::createFromFormat('d m Y H:i', $startStr);
            $endTs   = DateTime::createFromFormat('d m Y H:i', $endStr);

            $sTime = $startTs ? $startTs->getTimestamp() : 0;
            $eTime = $endTs ? $endTs->getTimestamp() : 0;

            $item = [
                'name'       => $name,
                'district'   => 'Merkez',
                'address'    => $address,
                'phone'      => $phone,
                'duty_hours' => "$startStr - $endStr",
                'map_url'    => "https://www.google.com/maps/search/?api=1&query=" . urlencode($name . ' Eczanesi Bartın'),
            ];

            if ($sTime <= $now && $now <= $eTime) {
                $activeNow[] = $this->createPharmacy($item);
            }

            if (strpos($startStr, $todayStr) === 0) {
                $todayUpcoming[] = $this->createPharmacy($item);
            }
        }

        $pharmacies = !empty($activeNow) ? $activeNow : $todayUpcoming;

        // If still empty, fallback to any entry covering today
        if (empty($pharmacies)) {
            foreach ($matches as $m) {
                if (strpos($m[1], $todayStr) === 0 || strpos($m[2], $todayStr) === 0) {
                    $pharmacies[] = $this->createPharmacy([
                        'name'       => trim($m[3]),
                        'district'   => 'Merkez',
                        'address'    => trim($m[5]),
                        'phone'      => trim($m[4]),
                        'duty_hours' => trim($m[1]) . ' - ' . trim($m[2]),
                        'map_url'    => "https://www.google.com/maps/search/?api=1&query=" . urlencode(trim($m[3]) . ' Eczanesi Bartın'),
                    ]);
                }
            }
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
