<?php

namespace Scrapers;

use App\Core\BaseScraper;
use App\Helpers\Str;

class TokatScraper extends BaseScraper
{
    protected string $cityName = 'Tokat';
    protected int $plateCode = 60;
    protected string $primaryUrl = 'https://www.tokateo.org/nobetcieczane.aspx';
    protected array $fallbackUrls = [
        'https://tokateo.org/nobetcieczane.aspx',
        'https://www.tokateo.org/NobetciEczane.aspx?Sayfa=Nobet'
    ];

    private array $bolgeler = [
        'TOKAT'       => 'Merkez',
        'ALMUS'       => 'Almus',
        'ARTOVA'      => 'Artova',
        'BAŞÇİFTLİK'  => 'Başçiftlik',
        'ERBAA'       => 'Erbaa',
        'niksar'      => 'Niksar',
        'PAZAR'       => 'Pazar',
        'REŞADİYE'    => 'Reşadiye',
        'SULUSARAY'   => 'Sulusaray',
        'TURHAL'      => 'Turhal',
        'YEŞİLYURT'   => 'Yeşilyurt',
        'ZİLE'        => 'Zile',
    ];

    public function scrape(?string $district = null): array
    {
        $gun = date('j');
        $ay  = date('n');
        $yil = date('Y');

        // Check if single district requested
        if (!empty($district)) {
            $matchedKey = null;
            $matchedName = null;
            foreach ($this->bolgeler as $key => $dName) {
                if (Str::contains($dName, $district) || Str::contains($district, $dName)) {
                    $matchedKey = $key;
                    $matchedName = $dName;
                    break;
                }
            }

            if ($matchedKey !== null) {
                $url = "https://www.tokateo.org/NBEczane.aspx?gun={$gun}&ay={$ay}&yil={$yil}&bolge=" . urlencode($matchedKey);
                $html = $this->fetchHtml($url);
                $pharmacies = $this->parseNBEczane($html, $matchedName);
                return $this->filterPharmacies($pharmacies, $district);
            }
        }

        // Parallel fetch for all districts
        $mh = curl_multi_init();
        $handles = [];

        foreach ($this->bolgeler as $bolgeKey => $distName) {
            $url = "https://www.tokateo.org/NBEczane.aspx?gun={$gun}&ay={$ay}&yil={$yil}&bolge=" . urlencode($bolgeKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$bolgeKey] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);

        $allPharmacies = [];

        foreach ($handles as $bolgeKey => $ch) {
            $content = (string)curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $distName = $this->bolgeler[$bolgeKey];
            $parsed = $this->parseNBEczane($content, $distName);
            foreach ($parsed as $p) {
                $allPharmacies[] = $p;
            }
        }

        curl_multi_close($mh);

        return $this->filterPharmacies($allPharmacies, $district);
    }

    private function parseNBEczane(string $html, string $districtName): array
    {
        $pharmacies = [];

        preg_match_all('/<div style="width:95%;\s*border:3px solid #000000;.*?<\/div>/is', $html, $cards);

        foreach ($cards[0] as $c) {
            $name = '';
            $phone = '';
            $address = '';

            if (preg_match('/<h1>(.*?)<\/h1>/is', $c, $nm)) {
                $name = trim(strip_tags($nm[1]));
                $name = preg_replace('/(\s+ECZ\.|\s+ECZANES[İI]|\s+ECZANE)$/ui', '', $name);
            }

            if (preg_match('/<h3>.*?TEL:\s*([^)]+)\)/is', $c, $pm)) {
                $phone = preg_replace('/[^\d]/', '', $pm[1]);
            }

            if (preg_match('/<h2>(.*?)<\/h2>/is', $c, $am)) {
                $address = trim(strip_tags(str_replace('&nbsp;', ' ', $am[1])));
            }

            if (!empty($name)) {
                $p = [
                    'name'     => $name,
                    'district' => $districtName,
                    'address'  => $address,
                    'phone'    => $phone,
                ];

                $pharmacies[] = $p;
            }
        }

        return $pharmacies;
    }
}
