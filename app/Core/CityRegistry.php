<?php

namespace App\Core;

use App\Helpers\Str;

class CityRegistry
{
    /**
     * Complete list of all 81 provinces with plate codes, filenames and chamber URLs
     */
    private static array $cities = [
        1  => ['name' => 'Adana', 'file' => 'Adana.php', 'class' => 'AdanaScraper', 'url' => 'https://www.adanaeo.org.tr/nobetci-eczaneler'],
        2  => ['name' => 'Adıyaman', 'file' => 'Adiyaman.php', 'class' => 'AdiyamanScraper', 'url' => 'https://www.adiyamaneo.org.tr/'],
        3  => ['name' => 'Afyonkarahisar', 'file' => 'Afyonkarahisar.php', 'class' => 'AfyonkarahisarScraper', 'url' => 'https://www.afyoneczaciodasi.org.tr/nobetci-eczaneler'],
        4  => ['name' => 'Ağrı', 'file' => 'Agri.php', 'class' => 'AgriScraper', 'url' => 'https://www.agrieo.org.tr/'],
        5  => ['name' => 'Amasya', 'file' => 'Amasya.php', 'class' => 'AmasyaScraper', 'url' => 'https://www.amasyaeo.org.tr/'],
        6  => ['name' => 'Ankara', 'file' => 'Ankara.php', 'class' => 'AnkaraScraper', 'url' => 'https://www.aeo.org.tr/nobetci-eczaneler'],
        7  => ['name' => 'Antalya', 'file' => 'Antalya.php', 'class' => 'AntalyaScraper', 'url' => 'https://www.antalyaeo.org.tr/tr/nobetci-eczaneler'],
        8  => ['name' => 'Artvin', 'file' => 'Artvin.php', 'class' => 'ArtvinScraper', 'url' => 'https://www.trabzoneczaciodasi.org.tr/nobetci-eczaneler/8'],
        9  => ['name' => 'Aydın', 'file' => 'Aydin.php', 'class' => 'AydinScraper', 'url' => 'https://www.aydineo.org.tr/'],
        10 => ['name' => 'Balıkesir', 'file' => 'Balikesir.php', 'class' => 'BalikesirScraper', 'url' => 'https://www.balikesireczaciodasi.org.tr/nobetci-eczaneler'],
        11 => ['name' => 'Bilecik', 'file' => 'Bilecik.php', 'class' => 'BilecikScraper', 'url' => 'https://www.bilecikeo.org.tr/'],
        12 => ['name' => 'Bingöl', 'file' => 'Bingol.php', 'class' => 'BingolScraper', 'url' => 'https://www.bingoleo.org.tr/'],
        13 => ['name' => 'Bitlis', 'file' => 'Bitlis.php', 'class' => 'BitlisScraper', 'url' => 'https://www.bitliseo.org.tr/'],
        14 => ['name' => 'Bolu', 'file' => 'Bolu.php', 'class' => 'BoluScraper', 'url' => 'https://www.bolueo.org.tr/'],
        15 => ['name' => 'Burdur', 'file' => 'Burdur.php', 'class' => 'BurdurScraper', 'url' => 'https://www.burdureo.org.tr/'],
        16 => ['name' => 'Bursa', 'file' => 'Bursa.php', 'class' => 'BursaScraper', 'url' => 'https://www.bursaecza.org.tr/'],
        17 => ['name' => 'Çanakkale', 'file' => 'Canakkale.php', 'class' => 'CanakkaleScraper', 'url' => 'https://www.canakkaleeo.org.tr/'],
        18 => ['name' => 'Çankırı', 'file' => 'Cankiri.php', 'class' => 'CankiriScraper', 'url' => 'https://www.cankirieczaciodasi.org.tr/'],
        19 => ['name' => 'Çorum', 'file' => 'Corum.php', 'class' => 'CorumScraper', 'url' => 'https://www.corumeo.org.tr/nobetci-eczaneler'],
        20 => ['name' => 'Denizli', 'file' => 'Denizli.php', 'class' => 'DenizliScraper', 'url' => 'https://www.denizli.bel.tr/Default.aspx?k=NobetciEczaneler'],
        21 => ['name' => 'Diyarbakır', 'file' => 'Diyarbakir.php', 'class' => 'DiyarbakirScraper', 'url' => 'https://www.diyarbakireo.org.tr/'],
        22 => ['name' => 'Edirne', 'file' => 'Edirne.php', 'class' => 'EdirneScraper', 'url' => 'https://www.edirneeczaciodasi.org.tr/'],
        23 => ['name' => 'Elazığ', 'file' => 'Elazig.php', 'class' => 'ElazigScraper', 'url' => 'https://www.elazigeo.org.tr/'],
        24 => ['name' => 'Erzincan', 'file' => 'Erzincan.php', 'class' => 'ErzincanScraper', 'url' => 'https://www.erzincaneo.org.tr/'],
        25 => ['name' => 'Erzurum', 'file' => 'Erzurum.php', 'class' => 'ErzurumScraper', 'url' => 'https://www.erzurumeo.org.tr/'],
        26 => ['name' => 'Eskişehir', 'file' => 'Eskisehir.php', 'class' => 'EskisehirScraper', 'url' => 'https://www.eskisehireo.org.tr/eskisehir-nobetci-eczaneler/'],
        27 => ['name' => 'Gaziantep', 'file' => 'Gaziantep.php', 'class' => 'GaziantepScraper', 'url' => 'https://gaziantep.bel.tr/tr/nobetci-eczaneler'],
        28 => ['name' => 'Giresun', 'file' => 'Giresun.php', 'class' => 'GiresunScraper', 'url' => 'https://www.giresuneo.org.tr/'],
        29 => ['name' => 'Gümüşhane', 'file' => 'Gumushane.php', 'class' => 'GumushaneScraper', 'url' => 'https://www.trabzoneczaciodasi.org.tr/'],
        30 => ['name' => 'Hakkari', 'file' => 'Hakkari.php', 'class' => 'HakkariScraper', 'url' => 'https://www.vaneczaciodasi.org.tr/'],
        31 => ['name' => 'Hatay', 'file' => 'Hatay.php', 'class' => 'HatayScraper', 'url' => 'https://www.hatayeo.org.tr/'],
        32 => ['name' => 'Isparta', 'file' => 'Isparta.php', 'class' => 'IspartaScraper', 'url' => 'https://www.ispartaeo.org.tr/'],
        33 => ['name' => 'Mersin', 'file' => 'Mersin.php', 'class' => 'MersinScraper', 'url' => 'https://www.mersineo.org.tr/'],
        34 => ['name' => 'İstanbul', 'file' => 'Istanbul.php', 'class' => 'IstanbulScraper', 'url' => 'https://eczane.ibb.gov.tr/'],
        35 => ['name' => 'İzmir', 'file' => 'Izmir.php', 'class' => 'IzmirScraper', 'url' => 'https://www.izmir.bel.tr/tr/NobetciEczane/27'],
        36 => ['name' => 'Kars', 'file' => 'Kars.php', 'class' => 'KarsScraper', 'url' => 'https://www.erzurumeo.org.tr/'],
        37 => ['name' => 'Kastamonu', 'file' => 'Kastamonu.php', 'class' => 'KastamonuScraper', 'url' => 'https://www.kastamonueczaciodasi.org.tr/'],
        38 => ['name' => 'Kayseri', 'file' => 'Kayseri.php', 'class' => 'KayseriScraper', 'url' => 'https://cbs.kayseri.bel.tr/nobetcieczaneler.aspx'],
        39 => ['name' => 'Kırklareli', 'file' => 'Kirklareli.php', 'class' => 'KirklareliScraper', 'url' => 'https://www.kirklarelieo.org.tr/'],
        40 => ['name' => 'Kırşehir', 'file' => 'Kirsehir.php', 'class' => 'KirsehirScraper', 'url' => 'https://www.kirsehireo.org.tr/'],
        41 => ['name' => 'Kocaeli', 'file' => 'Kocaeli.php', 'class' => 'KocaeliScraper', 'url' => 'https://www.kocaeli.bel.tr/nobetci-eczaneler.html'],
        42 => ['name' => 'Konya', 'file' => 'Konya.php', 'class' => 'KonyaScraper', 'url' => 'https://www.konyaeo.org.tr/'],
        43 => ['name' => 'Kütahya', 'file' => 'Kutahya.php', 'class' => 'KutahyaScraper', 'url' => 'https://www.kutahyaeczaciodasi.org.tr/'],
        44 => ['name' => 'Malatya', 'file' => 'Malatya.php', 'class' => 'MalatyaScraper', 'url' => 'https://www.malatyaeo.org.tr/'],
        45 => ['name' => 'Manisa', 'file' => 'Manisa.php', 'class' => 'ManisaScraper', 'url' => 'https://www.manisaeczaciodasi.org.tr/nobetci-eczaneler'],
        46 => ['name' => 'Kahramanmaraş', 'file' => 'Kahramanmaras.php', 'class' => 'KahramanmarasScraper', 'url' => 'https://www.kmaras.eo.org.tr/'],
        47 => ['name' => 'Mardin', 'file' => 'Mardin.php', 'class' => 'MardinScraper', 'url' => 'https://www.mardineo.org.tr/'],
        48 => ['name' => 'Muğla', 'file' => 'Mugla.php', 'class' => 'MuglaScraper', 'url' => 'https://www.muglaeo.org.tr/'],
        49 => ['name' => 'Muş', 'file' => 'Mus.php', 'class' => 'MusScraper', 'url' => 'https://www.museo.org.tr/'],
        50 => ['name' => 'Nevşehir', 'file' => 'Nevsehir.php', 'class' => 'NevsehirScraper', 'url' => 'https://www.nevsehireo.org.tr/'],
        51 => ['name' => 'Niğde', 'file' => 'Nigde.php', 'class' => 'NigdeScraper', 'url' => 'https://www.nigdeeo.org.tr/'],
        52 => ['name' => 'Ordu', 'file' => 'Ordu.php', 'class' => 'OrduScraper', 'url' => 'https://www.ordueo.org.tr/'],
        53 => ['name' => 'Rize', 'file' => 'Rize.php', 'class' => 'RizeScraper', 'url' => 'https://www.rizeeo.org.tr/'],
        54 => ['name' => 'Sakarya', 'file' => 'Sakarya.php', 'class' => 'SakaryaScraper', 'url' => 'https://www.sakarya.bel.tr/a/EBelediye/NobetciEczaneler'],
        55 => ['name' => 'Samsun', 'file' => 'Samsun.php', 'class' => 'SamsunScraper', 'url' => 'https://www.samsuneo.org.tr/'],
        56 => ['name' => 'Siirt', 'file' => 'Siirt.php', 'class' => 'SiirtScraper', 'url' => 'https://www.siirteo.org.tr/'],
        57 => ['name' => 'Sinop', 'file' => 'Sinop.php', 'class' => 'SinopScraper', 'url' => 'https://www.sinopeo.org.tr/'],
        58 => ['name' => 'Sivas', 'file' => 'Sivas.php', 'class' => 'SivasScraper', 'url' => 'https://www.sivaseo.org.tr/'],
        59 => ['name' => 'Tekirdağ', 'file' => 'Tekirdag.php', 'class' => 'TekirdagScraper', 'url' => 'https://www.tekirdageo.org.tr/'],
        60 => ['name' => 'Tokat', 'file' => 'Tokat.php', 'class' => 'TokatScraper', 'url' => 'https://www.tokateo.org.tr/'],
        61 => ['name' => 'Trabzon', 'file' => 'Trabzon.php', 'class' => 'TrabzonScraper', 'url' => 'https://www.trabzoneczaciodasi.org.tr/'],
        62 => ['name' => 'Tunceli', 'file' => 'Tunceli.php', 'class' => 'TunceliScraper', 'url' => 'https://www.tuncelieo.org.tr/'],
        63 => ['name' => 'Şanlıurfa', 'file' => 'Sanliurfa.php', 'class' => 'SanliurfaScraper', 'url' => 'https://www.sanliurfaeo.org.tr/'],
        64 => ['name' => 'Uşak', 'file' => 'Usak.php', 'class' => 'UsakScraper', 'url' => 'https://www.usakeo.org.tr/'],
        65 => ['name' => 'Van', 'file' => 'Van.php', 'class' => 'VanScraper', 'url' => 'https://www.vaneczaciodasi.org.tr/'],
        66 => ['name' => 'Yozgat', 'file' => 'Yozgat.php', 'class' => 'YozgatScraper', 'url' => 'https://www.yozgateo.org.tr/'],
        67 => ['name' => 'Zonguldak', 'file' => 'Zonguldak.php', 'class' => 'ZonguldakScraper', 'url' => 'https://www.zonguldakeo.org.tr/'],
        68 => ['name' => 'Aksaray', 'file' => 'Aksaray.php', 'class' => 'AksarayScraper', 'url' => 'https://www.aksarayeo.org.tr/'],
        69 => ['name' => 'Bayburt', 'file' => 'Bayburt.php', 'class' => 'BayburtScraper', 'url' => 'https://www.erzurumeo.org.tr/'],
        70 => ['name' => 'Karaman', 'file' => 'Karaman.php', 'class' => 'KaramanScraper', 'url' => 'https://www.karamaneo.org.tr/'],
        71 => ['name' => 'Kırıkkale', 'file' => 'Kirikkale.php', 'class' => 'KirikkaleScraper', 'url' => 'https://www.kirikkaleeo.org.tr/'],
        72 => ['name' => 'Batman', 'file' => 'Batman.php', 'class' => 'BatmanScraper', 'url' => 'https://www.batmaneo.org.tr/'],
        73 => ['name' => 'Şırnak', 'file' => 'Sirnak.php', 'class' => 'SirnakScraper', 'url' => 'https://www.sirnakeo.org.tr/'],
        74 => ['name' => 'Bartın', 'file' => 'Bartin.php', 'class' => 'BartinScraper', 'url' => 'https://www.bartineo.org.tr/'],
        75 => ['name' => 'Ardahan', 'file' => 'Ardahan.php', 'class' => 'ArdahanScraper', 'url' => 'https://www.erzurumeo.org.tr/'],
        76 => ['name' => 'Iğdır', 'file' => 'Igdir.php', 'class' => 'IgdirScraper', 'url' => 'https://www.igdireo.org.tr/'],
        77 => ['name' => 'Yalova', 'file' => 'Yalova.php', 'class' => 'YalovaScraper', 'url' => 'https://www.yalovaeo.org.tr/'],
        78 => ['name' => 'Karabük', 'file' => 'Karabuk.php', 'class' => 'KarabukScraper', 'url' => 'https://www.zonguldakeo.org.tr/'],
        79 => ['name' => 'Kilis', 'file' => 'Kilis.php', 'class' => 'KilisScraper', 'url' => 'https://www.kiliseo.org.tr/'],
        80 => ['name' => 'Osmaniye', 'file' => 'Osmaniye.php', 'class' => 'OsmaniyeScraper', 'url' => 'https://www.osmaniyeeo.org.tr/'],
        81 => ['name' => 'Düzce', 'file' => 'Duzce.php', 'class' => 'DuzceScraper', 'url' => 'https://www.duzceeo.org.tr/']
    ];

    /**
     * Find city by name, plate number, or slug
     */
    public static function find(string $query): ?array
    {
        $cleanQuery = trim($query);

        // Check plate number
        if (is_numeric($cleanQuery)) {
            $plate = (int) $cleanQuery;
            if (isset(self::$cities[$plate])) {
                $city = self::$cities[$plate];
                $city['plate'] = $plate;
                return $city;
            }
        }

        $querySlug = Str::slug($cleanQuery);

        foreach (self::$cities as $plate => $city) {
            $nameSlug = Str::slug($city['name']);
            if ($querySlug === $nameSlug || Str::contains($city['name'], $cleanQuery)) {
                $city['plate'] = $plate;
                return $city;
            }
        }

        return null;
    }

    /**
     * Get all registered provinces
     */
    public static function all(): array
    {
        return self::$cities;
    }
}
