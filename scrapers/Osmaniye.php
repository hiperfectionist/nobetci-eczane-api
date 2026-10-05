<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class OsmaniyeScraper extends BaseScraper
{
    protected string $cityName = 'Osmaniye';
    protected int $plateCode = 80;
    protected string $primaryUrl = 'https://www.osmaniyeeo.org.tr/';
    protected array $fallbackUrls = array (
  0 => 'https://osmaniyeeo.org.tr/',
  1 => 'https://osmaniyeeo.org.tr/nobetci-eczaneler',
  2 => 'https://www.osmaniyeeo.org.tr/nobetci-eczaneler',
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
