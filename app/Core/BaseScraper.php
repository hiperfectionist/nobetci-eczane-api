<?php

namespace App\Core;

use App\Helpers\Str;

abstract class BaseScraper
{
    protected string $cityName = '';
    protected int $plateCode = 0;
    protected string $primaryUrl = '';
    protected array $fallbackUrls = [];

    /**
     * Main scrape contract: must return array of pharmacy records
     */
    abstract public function scrape(?string $district = null): array;

    public function getCityName(): string
    {
        return $this->cityName;
    }

    public function getPlateCode(): int
    {
        return $this->plateCode;
    }

    public function getPrimaryUrl(): string
    {
        return $this->primaryUrl;
    }

    /**
     * Standardized Pharmacy object constructor
     */
    protected function createPharmacy(array $item): array
    {
        $name = trim(Str::clean($item['name'] ?? ''));
        // Strip duplicate "ECZANESİ ECZANESİ"
        $name = preg_replace('/\s+(?:ECZANESİ|Eczanesi)\s+(?:ECZANESİ|Eczanesi)$/iu', ' Eczanesi', $name);
        // Ensure "ECZANESİ" or "Eczanesi" is formatted nicely
        if (!preg_match('/(?:ECZANESİ|Eczanesi|ECZANE|Eczane)$/iu', $name)) {
            $name .= ' Eczanesi';
        }

        $district = Str::clean($item['district'] ?? '');
        $address = Str::clean($item['address'] ?? '');
        $phone = $this->formatPhone($item['phone'] ?? '');
        $directions = Str::clean($item['directions'] ?? '');
        $dutyHours = Str::clean($item['duty_hours'] ?? '');

        $lat = isset($item['latitude']) && is_numeric($item['latitude']) ? (float) $item['latitude'] : null;
        $lng = isset($item['longitude']) && is_numeric($item['longitude']) ? (float) $item['longitude'] : null;

        $mapUrl = Str::clean($item['map_url'] ?? '');
        if (empty($mapUrl) && $lat !== null && $lng !== null) {
            $mapUrl = "https://www.google.com/maps?q={$lat},{$lng}";
        } elseif (!empty($mapUrl) && ($lat === null || $lng === null)) {
            $coords = $this->extractCoordinates($mapUrl);
            if ($coords['lat'] !== null) {
                $lat = $coords['lat'];
                $lng = $coords['lng'];
            }
        }

        return [
            'name'       => $name,
            'district'   => $district,
            'address'    => $address,
            'phone'      => $phone,
            'directions' => $directions,
            'duty_hours' => $dutyHours,
            'latitude'   => $lat,
            'longitude'  => $lng,
            'map_url'    => $mapUrl
        ];
    }

    /**
     * Format phone number cleanly
     */
    protected function formatPhone(?string $rawPhone): string
    {
        if (empty($rawPhone)) {
            return '';
        }
        $digits = preg_replace('/[^\d]/', '', $rawPhone);
        if (strlen($digits) === 10 && $digits[0] !== '0') {
            $digits = '0' . $digits;
        } elseif (strlen($digits) === 12 && substr($digits, 0, 2) === '90') {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Filter pharmacies list by target district
     */
    protected function filterPharmacies(array $pharmacies, ?string $district): array
    {
        if (empty($district)) {
            return array_values($pharmacies);
        }

        $districtMatches = [];
        $fallbackMatches = [];

        foreach ($pharmacies as $p) {
            if (!empty($p['district']) && Str::contains($p['district'], $district)) {
                $districtMatches[] = $p;
            } elseif (
                (!empty($p['address']) && Str::contains($p['address'], $district)) ||
                (!empty($p['directions']) && Str::contains($p['directions'], $district))
            ) {
                $fallbackMatches[] = $p;
            }
        }

        return !empty($districtMatches) ? array_values($districtMatches) : array_values($fallbackMatches);
    }

    /**
     * HTTP GET with modern desktop browser headers
     */
    protected function fetchHtml(string $url, array $customHeaders = [], int $timeout = 10): string
    {
        $ch = curl_init($url);
        $headers = array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Upgrade-Insecure-Requests: 1'
        ], $customHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '' // Enable all supported encodings (gzip, deflate, etc.)
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return '';
        }

        // Fix encoding if windows-1254 or ISO-8859-9
        if (preg_match('/charset=["\']?(windows-1254|iso-8859-9)/i', $response)) {
            $converted = @iconv('windows-1254', 'UTF-8//IGNORE', $response);
            if ($converted !== false) {
                $response = $converted;
            }
        }

        return $response;
    }

    /**
     * HTTP POST with modern desktop browser headers
     */
    protected function postHtml(string $url, array|string $postData, array $customHeaders = [], int $timeout = 10): string
    {
        $ch = curl_init($url);
        $headers = array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        ], $customHeaders);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => is_array($postData) ? http_build_query($postData) : $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => ''
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return '';
        }

        if (preg_match('/charset=["\']?(windows-1254|iso-8859-9)/i', $response)) {
            $converted = @iconv('windows-1254', 'UTF-8//IGNORE', $response);
            if ($converted !== false) {
                $response = $converted;
            }
        }

        return $response;
    }

    /**
     * HTTP GET for JSON
     */
    protected function fetchJson(string $url, array $customHeaders = [], int $timeout = 10): ?array
    {
        $headers = array_merge([
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        ], $customHeaders);

        $html = $this->fetchHtml($url, $headers, $timeout);
        if (empty($html)) {
            return null;
        }

        $json = @json_decode($html, true);
        return is_array($json) ? $json : null;
    }

    /**
     * Extract coordinates from URL string or query parameters
     */
    protected function extractCoordinates(string $str): array
    {
        $coords = ['lat' => null, 'lng' => null];
        if (preg_match('/(?:q|destination|place|loc|ll)=([0-9\.]+),([0-9\.]+)/i', $str, $matches)) {
            $coords['lat'] = (float) $matches[1];
            $coords['lng'] = (float) $matches[2];
        } elseif (preg_match('/@([0-9\.]+),([0-9\.]+)/i', $str, $matches)) {
            $coords['lat'] = (float) $matches[1];
            $coords['lng'] = (float) $matches[2];
        } elseif (preg_match('/([0-9]{2}\.[0-9]{4,}),\s*([0-9]{2}\.[0-9]{4,})/', $str, $matches)) {
            $coords['lat'] = (float) $matches[1];
            $coords['lng'] = (float) $matches[2];
        }
        return $coords;
    }

    /**
     * Generic parser for standard TEB Chamber portals (like Adana, Antalya, Afyon, Balıkesir, etc.)
     */
    protected function parseTebCards(string $html, ?string $targetDistrict = null): array
    {
        $pharmacies = [];

        // Match cards by common classes
        $cardPattern = '/<div[^>]*class=["\'][^"\']*(?:nobetci|nobet-item|card|col-md-12|col-sm-12|pharmacy-item)[^"\']*["\'][^>]*>(.*?)<\/div>\s*(?=<div[^>]*class=["\']|\z)/is';
        if (!preg_match_all($cardPattern, $html, $cards)) {
            // Fallback: match by table rows or generic regex
            return $this->parseHeuristicCards($html, $targetDistrict);
        }

        foreach ($cards[1] as $cardHtml) {
            // Find pharmacy title
            if (!preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/iu', $cardHtml, $titleMatch) &&
                !preg_match('/<strong[^>]*>(.*?(?:ECZANESİ|Eczanesi).*?)<\/strong>/iu', $cardHtml, $titleMatch) &&
                !preg_match('/<span[^>]*class=["\'][^"\']*(?:title|name|eczane-adi)[^"\']*["\'][^>]*>(.*?)<\/span>/iu', $cardHtml, $titleMatch)) {
                continue;
            }

            $rawTitle = strip_tags($titleMatch[1]);
            if ($this->isBlacklistedTitle($rawTitle)) {
                continue;
            }
            if (empty(trim($rawTitle)) || !preg_match('/(?:ECZANE|Eczane)/iu', $rawTitle)) {
                // If title doesn't contain Eczane, verify if it looks like a name
                if (strlen(trim($rawTitle)) < 3 || strlen(trim($rawTitle)) > 50) {
                    continue;
                }
            }

            // Extract phone
            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $cardHtml, $telMatch)) {
                $phone = $telMatch[1];
            } elseif (preg_match('/(?:\b0\s*\d{3}\s*\d{3}\s*\d{2}\s*\d{2}\b|\b0\d{10}\b)/', $cardHtml, $phoneMatch)) {
                $phone = $phoneMatch[0];
            }

            // Extract Map URL
            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google|goo\.gl)[^"\']+)["\']/i', $cardHtml, $mapMatch)) {
                $mapUrl = $mapMatch[1];
            }

            // Extract District if present
            $district = '';
            if (preg_match('/(?:İlçe|ilce|Bölge|bolge)\s*:\s*([^<]+)/iu', $cardHtml, $distMatch)) {
                $district = Str::clean($distMatch[1]);
            } elseif (preg_match('/<span[^>]*class=["\'][^"\']*(?:badge|district|ilce)[^"\']*["\'][^>]*>(.*?)<\/span>/iu', $cardHtml, $distMatch2)) {
                $district = Str::clean(strip_tags($distMatch2[1]));
            }

            // Extract Address
            $cleanCardText = strip_tags($cardHtml);
            $lines = array_filter(array_map('trim', explode("\n", $cleanCardText)));
            $address = '';
            foreach ($lines as $line) {
                if (preg_match('/(?:Mah|Cad|Sok|No:|Bulv|Avenue|Street|Mh\.|Cd\.|Sk\.)/iu', $line)) {
                    $address = $line;
                    break;
                }
            }

            if (empty($address)) {
                // Heuristic: line longer than 15 chars that is not the title or phone
                foreach ($lines as $line) {
                    if ($line !== $rawTitle && strlen($line) > 15 && !preg_match('/^\+?\d[\d\s\-]+$/', $line)) {
                        $address = $line;
                        break;
                    }
                }
            }

            $pharmacies[] = $this->createPharmacy([
                'name'     => $rawTitle,
                'district' => $district,
                'address'  => $address,
                'phone'    => $phone,
                'map_url'  => $mapUrl
            ]);
        }

        if (empty($pharmacies)) {
            return $this->parseHeuristicCards($html, $targetDistrict);
        }

        return $this->filterPharmacies($pharmacies, $targetDistrict);
    }

    /**
     * Universal Heuristic fallback parser
     */
    protected function parseHeuristicCards(string $html, ?string $targetDistrict = null): array
    {
        $pharmacies = [];

        // Match patterns where pharmacy name is in headers or strong tags
        preg_match_all('/(?:<h[1-6][^>]*>|<strong[^>]*>|<b[^>]*>)\s*([a-zA-ZÇĞİÖŞÜçğıöşü0-9\s\-]{3,40}\s+(?:ECZANESİ|Eczanesi|ECZANE|Eczane))\s*(?:<\/h[1-6]>|<\/strong>|<\/b>)(.*?)(?=(?:<h[1-6]|<strong|<b)\s*[a-zA-ZÇĞİÖŞÜçğıöşü0-9\s\-]{3,40}\s+(?:ECZANESİ|Eczanesi|ECZANE|Eczane)|\z)/isu', $html, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $b) {
            $name = Str::clean(strip_tags($b[1]));
            if ($this->isBlacklistedTitle($name)) {
                continue;
            }
            $body = $b[2];

            // Extract phone
            $phone = '';
            if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $body, $tel)) {
                $phone = $tel[1];
            } elseif (preg_match('/0\s*[2-5]\d{2}\s*\d{3}\s*\d{2}\s*\d{2}/', $body, $tel2)) {
                $phone = $tel2[0];
            }

            // Extract map
            $mapUrl = '';
            if (preg_match('/href=["\'](https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google|goo\.gl)[^"\']+)["\']/i', $body, $map)) {
                $mapUrl = $map[1];
            }

            // Extract district
            $district = '';
            if (preg_match('/(?:İlçe|ilce|Bölge)\s*:\s*([^<]+)/iu', $body, $dist)) {
                $district = Str::clean($dist[1]);
            }

            // Extract address
            $bodyText = strip_tags($body);
            $lines = array_filter(array_map('trim', explode("\n", $bodyText)));
            $address = '';
            foreach ($lines as $line) {
                if (preg_match('/(?:Mah|Cad|Sok|No:|Bulv|Hastane|Köyü|Mevkii)/iu', $line)) {
                    $address = $line;
                    break;
                }
            }

            $pharmacies[] = $this->createPharmacy([
                'name'     => $name,
                'district' => $district,
                'address'  => $address,
                'phone'    => $phone,
                'map_url'  => $mapUrl
            ]);
        }

        return $this->filterPharmacies($pharmacies, $targetDistrict);
    }

    /**
     * Check if a title candidate is a false positive (cookies, notices, navigation headers, etc.)
     */
    protected function isBlacklistedTitle(string $title): bool
    {
        $normalized = Str::lowerTr($title);
        $blacklist = [
            'cerez', 'çerez', 'kvkk', 'gdpr', 'gizlilik', 'ayarlari', 'ayarları',
            'duyuru', 'duyurulari', 'duyuruları', 'teb duyuru', 'mevzuat', 'haberler',
            'hakkimizda', 'hakkımızda', 'iletisim', 'iletişim', 'yonetim', 'yönetim',
            'baskanimiz', 'başkanımız', 'etkinlik', 'menü', 'menu', 'giris', 'giriş',
            'nobetci eczaneler', 'nöbetçi eczaneler', 'eczane islemleri', 'eczane işlemleri',
            'arama sonuclari', 'arama sonuçları', 'e-kutuphane', 'e-kütüphane'
        ];

        foreach ($blacklist as $badWord) {
            if (mb_strpos($normalized, $badWord) !== false) {
                return true;
            }
        }

        return false;
    }
}
