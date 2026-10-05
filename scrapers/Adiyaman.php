<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class AdiyamanScraper extends BaseScraper
{
    protected string $cityName = 'Adıyaman';
    protected int $plateCode = 2;
    protected string $primaryUrl = 'https://www.adiyamaneo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://adiyamaneo.org.tr/',
  1 => 'https://adiyamaneo.org.tr/nobetci-eczaneler',
  2 => 'https://www.adiyamaneo.org.tr/nobetci-eczaneler',
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
