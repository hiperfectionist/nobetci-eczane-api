<?php

namespace Scrapers;

use App\Core\BaseScraper;

class AfyonkarahisarScraper extends BaseScraper
{
    protected string $cityName = 'Afyonkarahisar';
    protected int $plateCode = 3;
    protected string $primaryUrl = 'https://www.afyoneczaciodasi.org.tr/nobetci-eczaneler';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
