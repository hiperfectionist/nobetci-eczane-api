<?php

namespace Scrapers;

use App\Core\BaseScraper;

class BalikesirScraper extends BaseScraper
{
    protected string $cityName = 'Balıkesir';
    protected int $plateCode = 10;
    protected string $primaryUrl = 'https://www.balikesireczaciodasi.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
