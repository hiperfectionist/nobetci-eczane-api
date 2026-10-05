<?php

namespace Scrapers;

use App\Core\BaseScraper;

class CorumScraper extends BaseScraper
{
    protected string $cityName = 'Çorum';
    protected int $plateCode = 19;
    protected string $primaryUrl = 'https://www.corumeo.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
