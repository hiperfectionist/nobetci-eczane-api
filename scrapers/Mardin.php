<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class MardinScraper extends BaseScraper
{
    protected string $cityName = 'Mardin';
    protected int $plateCode = 47;
    protected string $primaryUrl = 'https://www.mardineo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://mardineo.org.tr/',
  1 => 'https://mardineo.org.tr/nobetci-eczaneler',
  2 => 'https://www.mardineo.org.tr/nobetci-eczaneler',
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
