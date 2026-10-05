<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class IgdirScraper extends BaseScraper
{
    protected string $cityName = 'Iğdır';
    protected int $plateCode = 76;
    protected string $primaryUrl = 'https://www.igdireo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://igdireo.org.tr/',
  1 => 'https://igdireo.org.tr/nobetci-eczaneler',
  2 => 'https://www.igdireo.org.tr/nobetci-eczaneler',
);

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

        $pharmacies = $this->parseTebCards($html, $district);

        if (empty($pharmacies)) {
            $pharmacies = $this->parseHeuristicCards($html, $district);
        }

        return $this->filterPharmacies($pharmacies, $district);
    }
}
