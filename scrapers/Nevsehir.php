<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class NevsehirScraper extends BaseScraper
{
    protected string $cityName = 'Nevşehir';
    protected int $plateCode = 50;
    protected string $primaryUrl = 'https://www.nevsehireo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://nevsehireo.org.tr/',
  1 => 'https://nevsehireo.org.tr/nobetci-eczaneler',
  2 => 'https://www.nevsehireo.org.tr/nobetci-eczaneler',
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
