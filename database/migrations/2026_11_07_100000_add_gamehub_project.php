<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the GameHub case study to the Projects list. Only inserts when no project with this slug exists, so an
 * edit made later in Admin > Projects is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || DB::table('projects')->where('slug', 'gamehub')->exists()) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel 12, Livewire 3, Tailwind CSS, MySQL
Role: Design and full-stack development
Status: Live, syncing on its own

## The problem
My games are spread over Steam, PlayStation and Xbox, and each one keeps its own playtime and trophy list. I wanted one place that shows what I own, what I am playing and what I have finished, without typing any of it in by hand.

## What it does
- Syncs Steam, PlayStation and Xbox libraries on a schedule, every six hours, with no clicks
- Merges the same game from different platforms and lets me settle duplicates by hand
- Tracks playtime, a status (wishlist, backlog, playing, on hold, finished, dropped) and a rating from 1 to 10
- Reads trophies and achievements, with a platinum badge and a progress bar
- Suggests what to play next from the backlog
- Shows stats for hours played over a week, a month or a year
- Publishes a public page for visitors and a share card image

## How it is built
- Livewire 3 components for the library, filters, stats and the public page, so there is no separate front end to maintain
- One provider class per platform behind a single interface, so adding a platform means adding one class
- Queued jobs with their own timeouts, one per linked account, so a slow platform never holds up the others
- The scheduler runs the sync every six hours, time-to-beat lookups every night and a summary every month
- IGDB supplies covers, release dates and how long a game takes to beat
- A private area for the owner only, and a public page that is limited to safe fields
- Feature tests for syncing, trophies, matching and access, run by GitHub Actions on every push
- Deployed with Laravel Forge using zero-downtime releases

## Challenges
- Sony's trophy list does not share ids with its game list, so games are matched by title, ignoring edition words and Roman numerals
- A second sync on the same day must update the playtime snapshot instead of failing on it
- Keeping credentials, emails and private notes out of every public view

## Result
A live page at https://nikolasavic.dev/games that keeps itself up to date.
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel 12, Livewire 3, Tailwind CSS, MySQL
Rol: Tasarım ve full-stack geliştirme
Durum: Yayında, kendiliğinden senkronize oluyor

## Sorun
Oyunlarım Steam, PlayStation ve Xbox arasında dağınık durumda ve her platform kendi oynama süresini ve kupa listesini tutuyor. Elle hiçbir şey girmeden, sahip olduklarımı, oynadıklarımı ve bitirdiklerimi tek yerde görmek istedim.

## Ne yapıyor
- Steam, PlayStation ve Xbox kütüphanelerini her altı saatte bir, tek tıklama olmadan senkronize eder
- Farklı platformlardaki aynı oyunu birleştirir, kopyaları elle çözmeme izin verir
- Oynama süresini, durumu (istek listesi, bekleyen, oynuyor, beklemede, bitirdi, bıraktı) ve 1 ile 10 arası puanı takip eder
- Kupaları ve başarımları okur, platin rozeti ve ilerleme çubuğu gösterir
- Bekleyen oyunlar arasından sıradaki oyunu önerir
- Bir hafta, ay veya yıl için oynama süresi istatistikleri gösterir
- Ziyaretçiler için herkese açık bir sayfa ve paylaşım kartı görseli yayınlar

## Nasıl yapıldı
- Kütüphane, filtreler, istatistikler ve herkese açık sayfa için Livewire 3 bileşenleri; ayrı bir ön yüz projesi yok
- Her platform için tek bir arayüzün arkasında ayrı bir sağlayıcı sınıfı; yeni platform eklemek tek sınıf eklemek demek
- Her bağlı hesap için kendi zaman aşımı olan kuyruk işleri; yavaş bir platform diğerlerini bekletmez
- Zamanlayıcı senkronizasyonu her altı saatte, bitirme süresi sorgularını her gece, özeti her ay çalıştırır
- Kapaklar, çıkış tarihleri ve bitirme süreleri için IGDB
- Yalnızca sahibine açık özel alan ve sadece güvenli alanları gösteren herkese açık sayfa
- Senkronizasyon, kupalar, eşleştirme ve erişim için her push'ta GitHub Actions ile çalışan testler
- Laravel Forge ile kesintisiz sürümlerle yayınlanıyor

## Zorluklar
- Sony'nin kupa listesi oyun listesiyle aynı kimlikleri paylaşmıyor; bu yüzden oyunlar başlığa göre, sürüm kelimeleri ve Romen rakamları yok sayılarak eşleştiriliyor
- Aynı gün ikinci senkronizasyon, oynama süresi kaydını hata vermeden güncellemeli
- Kimlik bilgilerini, e-postaları ve özel notları her herkese açık görünümün dışında tutmak

## Sonuç
Kendini güncel tutan canlı bir sayfa: https://nikolasavic.dev/games
TXT;

        $now = now();

        DB::table('projects')->insert([
            'title' => 'GameHub',
            'title_en' => 'GameHub',
            'title_tr' => 'GameHub',
            'slug' => 'gamehub',
            'summary' => 'A game library that syncs Steam, PlayStation and Xbox on its own, tracks playtime, ratings and trophies, and shares a public page.',
            'summary_en' => 'A game library that syncs Steam, PlayStation and Xbox on its own, tracks playtime, ratings and trophies, and shares a public page.',
            'summary_tr' => 'Steam, PlayStation ve Xbox ile kendiliğinden senkronize olan, oynama süresi, puan ve kupaları takip eden ve herkese açık bir sayfa sunan oyun kütüphanesi.',
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'live_url' => 'https://nikolasavic.dev/games',
            'featured' => false,
            'sort_order' => 10,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('slug', 'gamehub')->delete();
    }
};
