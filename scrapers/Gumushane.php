<?php

namespace Scrapers;

class GumushaneScraper extends TrabzonScraper
{
    protected string $cityName = 'Gümüşhane';
    protected int $plateCode = 29;
    protected string $primaryUrl = 'https://www.trabzoneczaciodasi.org.tr/nobetci-eczaneler/29';
    protected array $fallbackUrls = ['https://www.trabzoneczaciodasi.org.tr/'];
}
