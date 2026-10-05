# Türkiye Nöbetçi Eczane API (PHP / JSON)

Türkiye'deki 81 ilin nöbetçi eczanelerini çeken ve standart JSON formatında sunan modüler, yüksek performanslı ve genişletilebilir REST API.

---

## 🌟 Öne Çıkan Özellikler

1. **Modüler İl Mimarisi (`scrapers/`):**
   - Her il için **ayrı ve bağımsız bir PHP dosyası** bulunur (örn. `scrapers/Istanbul.php`, `scrapers/Ankara.php`, `scrapers/Izmir.php`, ... `scrapers/Duzce.php`).
   - Bir ilin web sitesi veya veri yapısı değiştiğinde ya da bozulduğunda **yalnızca o ilin dosyası** düzenlenir; diğer 80 il kesinlikle etkilenmez.
2. **Akıllı İl & İlçe Eşleştirme:**
   - Türkçe karakter duyarlı normalizasyon (`İ` / `ı`, `Ş` / `ş`, `Ğ` / `ğ`, `Ü` / `ü`, `Ö` / `ö`, `Ç` / `ç`).
   - Hem şehir adı (`İstanbul`, `istanbul`, `ISTANBUL`) hem de plaka kodu (`34`, `06`, `35`) ile sorgulama desteği.
   - İlçe filtresi (`&ilce=Kadıköy`, `&ilce=kadikoy`, `&ilce=Çankaya`, `&ilce=Konak`).
3. **Önbellek (Cache) Katmanı:**
   - Nöbetçi eczaneler günde bir kez değiştiği için oda ve belediye sunucularına gereksiz yük bindirmemek adına 30 dakikalık yüksek hızlı dosya önbelleği (`cache/`).
   - `&refresh=1` parametresi ile anında önbelleği atlayıp taze veri çekme imkanı.
4. **Zengin ve Standart JSON Şeması:**
   - Eczane adı, ilçe, tam açık adres, telefon, yol tarifi/açıklama, nöbet saatleri, enlem/boylam koordinatları ve doğrudan Google Haritalar navigasyon linki.
5. **CORS Desteği:**
   - Herhangi bir frontend uygulamasından (Vue, React, Flutter, mobil uygulamalar) doğrudan çağrılabilir (`Access-Control-Allow-Origin: *`).

---

## 📁 Proje Dizin Yapısı

```
nobetci-eczane-api/
│
├── index.php                 # API Giriş Kapısı & Router (Parametre denetimi, Cache, Scraper Dispatcher)
├── config.php                # Genel ayarlar (Cache TTL, Timezone, cURL Timeout vb.)
├── test_api.php              # CLI üzerinden hızlı sorgulama ve test aracı
│
├── app/
│   ├── Core/
│   │   ├── BaseScraper.php   # Temel Scraper sınıfı (cURL motoru, parser'lar, veri temizleme)
│   │   ├── CityRegistry.php  # 81 ilin plaka, dosya, sınıf ve kaynak URL kayıt defteri
│   │   ├── Cache.php         # JSON tabanlı hızlı önbellek yöneticisi
│   │   └── Response.php      # Standart HTTP JSON yanıt oluşturucu
│   └── Helpers/
│       └── Str.php           # Türkçe karakter normalizasyonu ve slug aracı
│
├── cache/                    # Otomatik yönetilen önbellek dosyaları
│
└── scrapers/                 # 81 İlin Bağımsız Scraper Dosyaları (1 Dosya = 1 İl)
    ├── Adana.php             # Adana Eczacı Odası Scraper
    ├── Adiyaman.php
    ├── Afyonkarahisar.php
    ├── Agri.php
    ├── Amasya.php
    ├── Ankara.php            # Ankara Eczacı Odası JSON API Scraper
    ├── Antalya.php           # Antalya Eczacı Odası Scraper
    ├── Artvin.php
    ├── Aydin.php
    ├── Balikesir.php
    ├── Bursa.php             # Bursa Eczacı Odası Scraper
    ├── Canakkale.php
    ├── Corum.php
    ├── Denizli.php
    ├── Diyarbakir.php
    ├── Erzurum.php           # Erzurum Bölge Odası Scraper (Ardahan, Bayburt, Kars destekli)
    ├── Eskisehir.php
    ├── Gaziantep.php         # Gaziantep Büyükşehir Scraper
    ├── Hatay.php
    ├── Istanbul.php          # İBB Eczane GeoJSON API Scraper
    ├── Izmir.php             # İzmir Açık Veri API Scraper
    ├── Kayseri.php           # Kayseri CBS Tablo Scraper
    ├── Kocaeli.php           # Kocaeli Büyükşehir Harita & Liste Scraper
    ├── Konya.php             # Konya Eczacı Odası Resmi Portalı Scraper
    ├── Sakarya.php           # Sakarya Büyükşehir Akordiyon Scraper
    ├── Trabzon.php           # Trabzon Bölge Odası Scraper (Artvin, Gümüşhane destekli)
    ├── ... (81 ilin tamamı)
    └── Duzce.php
```

---

## 🚀 Kurulum ve Çalıştırma

### 1. PHP Yerleşik Sunucusu ile (Hızlı Başlangıç):
```bash
# Proje dizininde
php -S 127.0.0.1:8000
```

### 2. XAMPP ile:
Projeyi `C:\xampp\htdocs\nobetci-eczane-api` dizinine kopyalayıp Apache'yi başlatın:
`http://localhost/nobetci-eczane-api/?sehir=İstanbul`

---

## 📡 API Kullanım Örnekleri

### 1. Şehre Göre Tüm Nöbetçi Eczaneler:
```http
GET /?sehir=İstanbul
GET /?sehir=34
GET /?sehir=Ankara
GET /?sehir=06
```

### 2. İlçe Filtreli Sorgulama:
```http
GET /?sehir=İstanbul&ilce=Kadıköy
GET /?sehir=Ankara&ilce=Çankaya
GET /?sehir=İzmir&ilce=Konak
GET /?sehir=Bursa&ilce=Nilüfer
GET /?sehir=Antalya&ilce=Muratpaşa
GET /?sehir=Konya&ilce=Selçuklu
```

### 3. Önbelleği Zorla Yenileme (`refresh`):
```http
GET /?sehir=İstanbul&ilce=Kadıköy&refresh=1
```

### 4. 81 İlin Tam Listesini Alma:
```http
GET /?sehir=list
# veya
GET /?list=1
```

---

## 📋 Örnek JSON Çıktısı

```json
{
    "status": "success",
    "sehir": "İstanbul",
    "ilce": "Kadıköy",
    "tarih": "2026-10-05",
    "saat": "22:43:15",
    "count": 9,
    "plaka": 34,
    "cached": false,
    "kaynak": "https://eczane.ibb.gov.tr/",
    "scraper_file": "scrapers/Istanbul.php",
    "data": [
        {
            "name": "MODA ECZANESİ",
            "district": "Kadıköy",
            "address": "Caferağa Mahallesi, Moda Caddesi, 83B (Caferağa Mah.)",
            "phone": "02163470407",
            "directions": "",
            "duty_hours": "Bugün 18:30 - Yarın 08:30",
            "latitude": 40.985748270032616,
            "longitude": 29.025259315485904,
            "map_url": "https://www.google.com/maps?q=40.985748270033,29.025259315486"
        }
    ]
}
```

---

## 🛠️ Bir İlin Scraper'ı Bozulduğunda Nasıl Güncellenir?

Örneğin `scrapers/Istanbul.php` dosyasını açıp ilgili fonksiyonu doğrudan düzenleyebilirsiniz:

```php
namespace Scrapers;

use App\Core\BaseScraper;

class IstanbulScraper extends BaseScraper
{
    protected string $cityName = 'İstanbul';
    protected int $plateCode = 34;
    protected string $primaryUrl = 'https://eczane.ibb.gov.tr/';

    public function scrape(?string $district = null): array
    {
        // 1. Yeni API veya HTML çek
        // 2. $this->createPharmacy([...]) ile standart diziye çevir
        // 3. return $this->filterPharmacies($pharmacies, $district);
    }
}
```

`BaseScraper` sınıfında yer alan hazır yardımcı araçlar:
- `$this->fetchHtml($url)`: Modern tarayıcı başlıklarıyla cURL isteği atar, SSL hatalarını ve gzip/deflate'i çözer.
- `$this->fetchJson($url)`: JSON endpoint'lerini tek satırda diziye dönüştürür.
- `$this->createPharmacy([...])`: Çıktı şemasını otomatik formatlar.
- `$this->extractCoordinates($url)`: Google Maps linklerinden lat/lng koordinatlarını çıkarır.
- `$this->filterPharmacies($list, $district)`: İlçe bazlı filtrelemeyi Türkçe harf duyarlı yapar.
