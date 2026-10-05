<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class CankiriScraper extends BaseScraper
{
    protected string $cityName = 'Çankırı';
    protected int $plateCode = 18;
    protected string $primaryUrl = 'https://www.cankirieczaciodasi.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://cankirieczaciodasi.org.tr/',
  1 => 'https://cankirieczaciodasi.org.tr/nobetci-eczaneler',
  2 => 'https://www.cankirieczaciodasi.org.tr/nobetci-eczaneler',
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
