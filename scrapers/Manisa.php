<?php

namespace Scrapers;

use App\Core\BaseScraper;

class ManisaScraper extends BaseScraper
{
    protected string $cityName = 'Manisa';
    protected int $plateCode = 45;
    protected string $primaryUrl = 'https://www.manisaeczaciodasi.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
