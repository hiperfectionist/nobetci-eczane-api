<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class KirklareliScraper extends BaseScraper
{
    protected string $cityName = 'Kırklareli';
    protected int $plateCode = 39;
    protected string $primaryUrl = 'https://www.kirklarelieo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://kirklarelieo.org.tr/',
  1 => 'https://kirklarelieo.org.tr/nobetci-eczaneler',
  2 => 'https://www.kirklarelieo.org.tr/nobetci-eczaneler',
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
