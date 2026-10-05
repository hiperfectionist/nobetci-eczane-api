<?php

namespace Scrapers;

class ArtvinScraper extends TrabzonScraper
{
    protected string $cityName = 'Artvin';
    protected int $plateCode = 8;
    protected string $primaryUrl = 'https://www.trabzoneczaciodasi.org.tr/nobetci-eczaneler/8';
    protected array $fallbackUrls = ['https://www.artvineo.org.tr/'];
}
