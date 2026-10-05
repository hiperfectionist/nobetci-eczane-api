<?php

namespace Scrapers;

use App\Core\BaseScraper;

class CanakkaleScraper extends BaseScraper
{
    protected string $cityName = 'Çanakkale';
    protected int $plateCode = 17;
    protected string $primaryUrl = 'https://www.canakkaleeo.org.tr/';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
