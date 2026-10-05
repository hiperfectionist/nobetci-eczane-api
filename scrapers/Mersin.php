<?php

namespace Scrapers;

use App\Core\BaseScraper;

class MersinScraper extends BaseScraper
{
    protected string $cityName = 'Mersin';
    protected int $plateCode = 33;
    protected string $primaryUrl = 'https://www.mersineczaciodasi.org.tr/';
    protected array $fallbackUrls = ['https://www.mersineo.org.tr/'];

    public function scrape(?string $district = null): array
    {
        $html = $this->fetchHtml($this->primaryUrl);
        if (empty($html)) {
            $html = $this->fetchHtml($this->fallbackUrls[0]);
        }

        if (empty($html)) {
            return [];
        }

        return $this->parseTebCards($html, $district);
    }
}
