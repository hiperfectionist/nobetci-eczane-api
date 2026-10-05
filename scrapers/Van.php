<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class VanScraper extends BaseScraper
{
    protected string $cityName = 'Van';
    protected int $plateCode = 65;
    protected string $primaryUrl = 'https://www.vaneczaciodasi.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
