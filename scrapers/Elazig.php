<?php

namespace Scrapers;

class ElazigScraper extends BingolScraper
{
    protected string $cityName = 'Elazığ';
    protected int $plateCode = 23;
    protected string $primaryUrl = 'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler/elazig';
    protected array $fallbackUrls = [
        'https://elazigeczaciodasi.org.tr/nobetci-eczaneler/elazig',
        'https://www.elazigeczaciodasi.org.tr/nobetci-eczaneler'
    ];
}
