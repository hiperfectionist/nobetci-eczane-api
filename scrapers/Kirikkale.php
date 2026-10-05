<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KirikkaleScraper extends BaseScraper
{
    protected string $cityName = 'Kırıkkale';
    protected int $plateCode = 71;
    protected string $primaryUrl = 'https://www.kirikkaleeo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://kirikkaleeo.org.tr/',
  1 => 'https://kirikkaleeo.org.tr/nobetci-eczaneler',
  2 => 'https://www.kirikkaleeo.org.tr/nobetci-eczaneler',
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
