<?php

namespace Scrapers;

use App\Core\BaseScraper;

class DiyarbakirScraper extends BaseScraper
{
    protected string $cityName = 'Diyarbakır';
    protected int $plateCode = 21;
    protected string $primaryUrl = 'https://www.diyarbakireo.org.tr/';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
