<?php

namespace Scrapers;

class TunceliScraper extends BingolScraper
{
    protected string $cityName = 'Tunceli';
    protected int $plateCode = 62;
    protected string $primaryUrl = 'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler/tunceli';
    protected array $fallbackUrls = [
        'https://elazigeczaciodasi.org.tr/nobetci-eczaneler/tunceli',
        'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler'
    ];
}
