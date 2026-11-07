<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the RunLog case study to the Projects list. Inserts only when no project with this slug exists, so a
 * later edit in Admin > Projects is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || DB::table('projects')->where('slug', 'runlog')->exists()) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel, Livewire, Filament, Horizon, Redis, Strava API, Iyzico
Role: Founder and developer
Status: Live

## The problem
Running apps record the numbers, but a runner still has to work out what they mean: how hard to train for the next race, when to rest and which finish time is realistic. RunLog turns the runs a runner already records on Strava into that advice, in Turkish and English, for the Turkish running community.

## What it does
- Connects to Strava and imports every run automatically, new ones as soon as they are recorded
- Shows each run with heart-rate zones, cadence and an age-graded score
- Writes a short coach note for every run and a weekly summary email, in the runner's language
- Builds a training plan for a target race, adapts it as the runner trains and notices missed sessions
- Predicts finish times for the common distances from recent runs
- Warns about injury risk, for example when weekly distance jumps by more than 10 percent
- Ranks runners, runs challenges and tracks segments
- Gives coaches a team view with a page for each athlete and a PDF team report
- Lists Turkish races in a calendar and a race database, and updates pace zones from race results
- Makes a share card for any run
- Sells a single race plan and a season pass through Iyzico

## How it is built
- Livewire pages for the whole app, with a Filament admin for runs, users, subscriptions and payments
- Strava sign-in with Socialite and a webhook, so new activities arrive without the runner doing anything
- Laravel Horizon on Redis runs the queues for imports, coach notes, plans, digests and rankings
- The scheduler recalculates rankings every hour, checks for missed sessions every Monday and sends the weekly digests
- AI calls run in queued jobs with timeouts and retries, so a slow answer never blocks a page
- Finish predictions use the Riegel formula on the last eight weeks of runs
- A Python script builds the team PDFs, with a font that renders Turkish characters correctly
- Full English and Turkish translations, and Turkish legal pages including KVKK
- Iyzico checkout with a payment callback and subscription records

## Challenges
- AI advice that does not invent facts: an early version of the coach note made up athlete names, which was found and fixed
- Turkish text in PDFs: characters came out wrong until the font was switched to one that supports them
- Keeping every screen, email and legal page in two languages
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel, Livewire, Filament, Horizon, Redis, Strava API, Iyzico
Rol: Kurucu ve geliştirici
Durum: Yayında

## Sorun
Koşu uygulamaları rakamları kaydeder, ancak bu rakamların ne anlama geldiğini koşucunun kendisi bulmak zorundadır: bir sonraki yarış için ne kadar sert antrenman yapmalı, ne zaman dinlenmeli ve hangi bitiş süresi gerçekçi. RunLog, koşucunun Strava'da zaten kaydettiği koşuları Türk koşu topluluğu için Türkçe ve İngilizce tavsiyeye dönüştürür.

## Ne yapıyor
- Strava'ya bağlanır ve her koşuyu otomatik içe aktarır, yenileri kaydedilir kaydedilmez
- Her koşuyu kalp atış bölgeleri, adım sıklığı ve yaş katsayılı bir puanla gösterir
- Her koşu için kısa bir koç notu ve haftalık bir özet e-postası yazar, koşucunun dilinde
- Hedef bir yarış için antrenman planı hazırlar, koşucu ilerledikçe planı uyarlar ve kaçırılan antrenmanları fark eder
- Son koşulardan yaygın mesafeler için bitiş süresi tahmin eder
- Sakatlık riskini uyarır; örneğin haftalık mesafe yüzde 10'dan fazla arttığında
- Koşucuları sıralar, mücadeleler düzenler ve segmentleri takip eder
- Koçlara her sporcu için ayrı sayfa ve PDF takım raporu olan bir takım görünümü sunar
- Türkiye'deki yarışları takvimde ve yarış veritabanında listeler, yarış sonuçlarından tempo bölgelerini günceller
- Her koşu için bir paylaşım kartı üretir
- Iyzico ile tek yarış planı ve sezon bileti satar

## Nasıl yapıldı
- Tüm uygulama için Livewire sayfaları; koşular, kullanıcılar, abonelikler ve ödemeler için bir Filament yönetim paneli
- Socialite ile Strava girişi ve bir webhook; yeni aktiviteler koşucu hiçbir şey yapmadan gelir
- Redis üzerinde Laravel Horizon; içe aktarma, koç notları, planlar, özetler ve sıralamalar için kuyrukları çalıştırır
- Zamanlayıcı sıralamaları her saat hesaplar, kaçırılan antrenmanları her pazartesi kontrol eder ve haftalık özetleri gönderir
- Yapay zekâ çağrıları zaman aşımı ve yeniden denemeli kuyruk işlerinde çalışır; yavaş bir yanıt hiçbir sayfayı bloklamaz
- Bitiş tahminleri son sekiz haftalık koşulara Riegel formülünü uygular
- Takım PDF'lerini bir Python betiği üretir; Türkçe karakterleri doğru gösteren bir yazı tipi kullanılır
- Tam İngilizce ve Türkçe çeviriler ve KVKK dahil Türkçe yasal sayfalar
- Ödeme geri dönüşü ve abonelik kayıtlarıyla Iyzico ödemesi

## Zorluklar
- Gerçek uydurmayan yapay zekâ tavsiyesi: koç notunun ilk sürümü sporcu adları uyduruyordu; bu fark edildi ve düzeltildi
- PDF'lerde Türkçe metin: yazı tipi bu karakterleri destekleyen bir tanesiyle değiştirilene kadar karakterler yanlış çıkıyordu
- Her ekranı, e-postayı ve yasal sayfayı iki dilde tutmak
TXT;

        $now = now();

        DB::table('projects')->insert([
            'title' => 'RunLog',
            'title_en' => 'RunLog',
            'title_tr' => 'RunLog',
            'slug' => 'runlog',
            'summary' => 'A training platform for Turkish runners that turns Strava runs into coach notes, training plans, race predictions and injury warnings.',
            'summary_en' => 'A training platform for Turkish runners that turns Strava runs into coach notes, training plans, race predictions and injury warnings.',
            'summary_tr' => 'Strava koşularını koç notlarına, antrenman planlarına, yarış tahminlerine ve sakatlık uyarılarına dönüştüren, Türk koşucular için bir antrenman platformu.',
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'live_url' => 'https://runlog.app',
            'featured' => false,
            'sort_order' => 50,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('slug', 'runlog')->delete();
    }
};
