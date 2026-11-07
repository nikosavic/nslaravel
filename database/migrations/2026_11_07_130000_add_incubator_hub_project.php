<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Incubator Hub case study to the Projects list. Inserts only when no project with this slug exists, so a
 * later edit in Admin > Projects is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || DB::table('projects')->where('slug', 'incubator-hub')->exists()) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel, Livewire, Reverb, Chart.js, Tailwind CSS, ESP32, Modbus TCP
Role: Full-stack development

## The problem
An incubator has to stay inside a narrow temperature range, and the backup generator that keeps it running needs watching too. Hardware like this fails quietly. Incubator Hub collects the readings from both and tells you the moment something goes wrong.

## What it does
- An ESP32 sensor posts the incubator's temperature and humidity to a secured API
- A live dashboard shows the current reading, a trend chart of the last 50 readings and the lowest and highest temperature of the last 24 hours, without a page refresh
- A Telegram message goes out when the temperature leaves the safe range of 37 to 39 °C
- A second dashboard reads a generator controller over Modbus TCP: phase voltages, battery voltage, engine speed, oil pressure, engine temperature, fuel level, engine state and breaker state
- A history page lists every logged reading
- Both dashboards sit behind a login

## How it is built
- The ESP32 sends an API key with every request, and the API validates each reading before it is stored
- Laravel Reverb pushes every new reading to the browser over WebSockets, and Laravel Echo updates the numbers and the Chart.js chart as they arrive
- An artisan command polls the generator with a Modbus client, converts the raw register values into real units and stores each poll as a log entry
- A Livewire component keeps the generator panel fresh
- A failed Telegram call is logged and never blocks the reading from being saved

## Challenges
- Reading industrial hardware reliably: the controller's registers have to be read as one aligned block, because several small reads came back shifted
- Live data without hammering the server: the model itself broadcasts when a reading is saved, so the browser never has to ask
- Alerts that cannot lose data: a notification problem must not cost a reading
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel, Livewire, Reverb, Chart.js, Tailwind CSS, ESP32, Modbus TCP
Rol: Full-stack geliştirme

## Sorun
Bir kuluçka makinesinin dar bir sıcaklık aralığında kalması gerekir ve onu çalışır tutan yedek jeneratör de izlenmelidir. Bu tür donanımlar sessizce arızalanır. Incubator Hub her ikisinden de okumaları toplar ve bir şey ters gittiği anda haber verir.

## Ne yapıyor
- Bir ESP32 sensörü kuluçka makinesinin sıcaklık ve nem değerlerini güvenli bir API'ye gönderir
- Canlı bir pano, anlık değeri, son 50 okumanın grafiğini ve son 24 saatin en düşük ve en yüksek sıcaklığını sayfayı yenilemeden gösterir
- Sıcaklık 37 ile 39 °C arasındaki güvenli aralığın dışına çıktığında Telegram mesajı gider
- İkinci bir pano, jeneratör kontrolcüsünü Modbus TCP üzerinden okur: faz gerilimleri, akü gerilimi, motor devri, yağ basıncı, motor sıcaklığı, yakıt seviyesi, motor durumu ve şalter durumu
- Geçmiş sayfası kaydedilen tüm okumaları listeler
- Her iki pano da giriş arkasındadır

## Nasıl yapıldı
- ESP32 her istekte bir API anahtarı gönderir; API her okumayı kaydetmeden önce doğrular
- Laravel Reverb her yeni okumayı WebSocket ile tarayıcıya iletir; Laravel Echo sayıları ve Chart.js grafiğini anında günceller
- Bir artisan komutu jeneratörü Modbus istemcisiyle sorgular, ham register değerlerini gerçek birimlere çevirir ve her sorguyu bir kayıt olarak saklar
- Bir Livewire bileşeni jeneratör panelini güncel tutar
- Başarısız bir Telegram çağrısı kaydedilir ve okumanın saklanmasını asla engellemez

## Zorluklar
- Endüstriyel donanımı güvenilir okumak: kontrolcünün register'ları tek ve hizalı bir blok olarak okunmalıdır, çünkü birkaç küçük okuma kaymış değerler döndürdü
- Sunucuyu yormadan canlı veri: okuma kaydedildiğinde model kendisi yayın yapar, bu yüzden tarayıcının sormasına gerek kalmaz
- Veri kaybettirmeyen uyarılar: bir bildirim sorunu bir okumaya mal olmamalıdır
TXT;

        $now = now();

        DB::table('projects')->insert([
            'title' => 'Incubator Hub',
            'title_en' => 'Incubator Hub',
            'title_tr' => 'Incubator Hub',
            'slug' => 'incubator-hub',
            'summary' => 'A live monitoring dashboard for an ESP32 incubator sensor and a generator controller, with charts, history and Telegram alerts.',
            'summary_en' => 'A live monitoring dashboard for an ESP32 incubator sensor and a generator controller, with charts, history and Telegram alerts.',
            'summary_tr' => 'ESP32 kuluçka sensörü ve jeneratör kontrolcüsü için grafikler, geçmiş ve Telegram uyarıları sunan canlı bir izleme panosu.',
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'repo_url' => null,
            'featured' => false,
            'sort_order' => 30,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('slug', 'incubator-hub')->delete();
    }
};
