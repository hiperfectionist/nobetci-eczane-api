<?php

namespace Scrapers;

use App\Core\BaseScraper;

class EskisehirScraper extends BaseScraper
{
    protected string $cityName = 'Eskişehir';
    protected int $plateCode = 26;
    protected string $primaryUrl = 'https://www.eskisehireo.org.tr/eskisehir-nobetci-eczaneler/';

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
