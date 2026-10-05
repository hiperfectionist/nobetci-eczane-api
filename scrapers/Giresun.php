<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class GiresunScraper extends BaseScraper
{
    protected string $cityName = 'Giresun';
    protected int $plateCode = 28;
    protected string $primaryUrl = 'https://www.giresuneczaciodasi.org.tr/api/pharmacies';
    protected array $fallbackUrls = [
        'https://giresuneczaciodasi.org.tr/api/pharmacies',
        'https://www.giresuneczaciodasi.org.tr/giresun_nobetci_eczaneler'
    ];

    public function scrape(?string $district = null): array
    {
        $json = $this->fetchJson($this->primaryUrl);

        if (empty($json)) {
            foreach ($this->fallbackUrls as $fallback) {
                if (str_contains($fallback, '/api/')) {
                    $json = $this->fetchJson($fallback);
                    if (!empty($json)) {
                        break;
                    }
                }
            }
        }

        if (empty($json) || !is_array($json)) {
            return [];
        }

        // Determine current duty date
        $targetDate = date('d.m.Y');
        if ((int) date('H') < 9) {
            $prevDate = date('d.m.Y', strtotime('-1 day'));
            foreach ($json as $item) {
                if (($item['date'] ?? '') === $prevDate) {
                    $targetDate = $prevDate;
                    break;
                }
            }
        }

        // Filter items for target date
        $dateItems = [];
        foreach ($json as $item) {
            if (($item['date'] ?? '') === $targetDate) {
                $dateItems[] = $item;
            }
        }

        // Fallback to today if empty
        if (empty($dateItems) && $targetDate !== date('d.m.Y')) {
            $targetDate = date('d.m.Y');
            foreach ($json as $item) {
                if (($item['date'] ?? '') === $targetDate) {
                    $dateItems[] = $item;
                }
            }
        }

        // Fallback to all items if date not matched
        if (empty($dateItems)) {
            $dateItems = $json;
        }

        $pharmacies = [];
        foreach ($dateItems as $item) {
            $name = trim($item['name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $dist = trim($item['district'] ?? '');
            $addr = trim($item['address'] ?? '');
            $phone = trim($item['phone'] ?? '');
            $directions = trim($item['tarif'] ?? '');
            $dutyStatus = trim($item['duty_status'] ?? '');
            $lat = isset($item['lat']) && is_numeric($item['lat']) ? (float) $item['lat'] : null;
            $lng = isset($item['lng']) && is_numeric($item['lng']) ? (float) $item['lng'] : null;

            $mapUrl = '';
            if ($lat !== null && $lng !== null) {
                $mapUrl = "https://www.google.com/maps?q={$lat},{$lng}";
            }

            $pharmacies[] = $this->createPharmacy([
                'name'       => $name,
                'district'   => $dist,
                'address'    => $addr,
                'directions' => $directions,
                'duty_hours' => $dutyStatus,
                'phone'      => $phone,
                'latitude'   => $lat,
                'longitude'  => $lng,
                'map_url'    => $mapUrl
            ]);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
