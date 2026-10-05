<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class DenizliScraper extends BaseScraper
{
    protected string $cityName = 'Denizli';
    protected int $plateCode = 20;
    protected string $primaryUrl = 'https://www.denizlieczaciodasi.org.tr/';
    protected array $fallbackUrls = ['https://www.denizli.bel.tr/hizli-erisim/nobetci-eczaneler'];

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
