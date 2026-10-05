<?php

namespace Scrapers;

use App\Core\BaseScraper;

class HatayScraper extends BaseScraper
{
    protected string $cityName = 'Hatay';
    protected int $plateCode = 31;
    protected string $primaryUrl = 'https://www.hatayeo.org.tr/';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
