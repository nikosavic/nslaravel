<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Tuneout case study to the Projects list. Inserts only when no project with this slug exists, so a
 * later edit in Admin > Projects is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || DB::table('projects')->where('slug', 'tuneout')->exists()) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel, Tailwind CSS, MySQL, Redis, Horizon, Gemini, Remotion, Iyzico
Role: Sole developer, from idea to build
Status: Work in progress

## The problem
Getting your music in front of the right people takes an enormous amount of time, and most of that time has nothing to do with the music itself. Cold emails, press release templates, media lists: it is a full-time job that most independent artists cannot afford to hire for.

## What it does
Tuneout handles the mechanical parts of music promotion, the research, the writing and the outreach, so artists can focus on the creative work. It is built for independent musicians, bands and labels.
- Analyses a release from the uploaded track and the artist's details
- Matches the release with real contacts: music journalists, bloggers, playlist curators, radio programmers, podcasts and YouTube reactors
- Writes a personalised pitch for each contact, relevant to their coverage area and in a tone that respects their time
- Sends the pitch, tracks when it is opened and follows up when it was not
- Builds a public press kit page and a downloadable EPK as a PDF
- Makes promo videos: a release announcement, a story reel and a Spotify Canvas
- Keeps a task checklist for each campaign and a report when it ends

## How it is built
- Google Gemini listens to the track and describes it; that analysis feeds the pitches, the press kit and the videos
- Long jobs run on Laravel Horizon queues with timeouts, retries and back-off, so a slow analysis or render never blocks a page
- Remotion renders the videos from React compositions, and Python scripts build the PDFs
- A contact database with full-text search, de-duplication, email verification and unsubscribe handling
- Credits bought through Iyzico, with a log of every credit change and refunds from the admin
- A scheduler sends the ready emails every five minutes, the follow-ups every day, verifies contacts every week and clears old campaign media after 30 days
- An admin area for users, campaigns, contacts, payments, support tickets and surveys
- Legal pages, data export and account deletion for GDPR

## Challenges
- Pitches that do not read like mass mail: every pitch is built from the release analysis and the contact's own coverage, from templates refined until they sound like a person wrote them
- Heavy work such as audio analysis, video rendering and PDF building has to stay off the request cycle and still report back to the artist
- Keeping a contact list clean: duplicates, bad addresses and people who asked not to be contacted

## Status
Tuneout is a work in progress.
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel, Tailwind CSS, MySQL, Redis, Horizon, Gemini, Remotion, Iyzico
Rol: Fikirden uygulamaya tek geliştirici
Durum: Geliştirme sürüyor

## Sorun
Müziğinizi doğru kişilere ulaştırmak çok zaman alır ve bu zamanın çoğunun müzikle ilgisi yoktur. Soğuk e-postalar, basın bülteni şablonları, medya listeleri: bağımsız sanatçıların çoğunun işe alamayacağı, tam zamanlı bir iştir.

## Ne yapıyor
Tuneout, müzik tanıtımının mekanik kısımlarını, yani araştırmayı, yazmayı ve ulaşmayı üstlenir; böylece sanatçılar yaratıcı işe odaklanabilir. Bağımsız müzisyenler, gruplar ve plak şirketleri için geliştirildi.
- Yüklenen parçadan ve sanatçı bilgilerinden bir çıkışı analiz eder
- Çıkışı gerçek kişilerle eşleştirir: müzik gazetecileri, blog yazarları, çalma listesi küratörleri, radyo programcıları, podcastler ve YouTube yorumcuları
- Her kişi için, onun alanına uygun ve zamanına saygı duyan bir tonda kişiselleştirilmiş bir tanıtım e-postası yazar
- E-postayı gönderir, açılıp açılmadığını izler ve açılmadıysa takip eder
- Herkese açık bir basın kiti sayfası ve indirilebilir bir EPK PDF'i hazırlar
- Tanıtım videoları üretir: çıkış duyurusu, hikaye videosu ve Spotify Canvas
- Her kampanya için bir görev listesi ve kampanya sonunda bir rapor tutar

## Nasıl yapıldı
- Google Gemini parçayı dinler ve tanımlar; bu analiz tanıtım e-postalarını, basın kitini ve videoları besler
- Uzun işler zaman aşımı, yeniden deneme ve bekleme süreleriyle Laravel Horizon kuyruklarında çalışır; yavaş bir analiz veya render hiçbir sayfayı bloklamaz
- Videoları Remotion React bileşenlerinden üretir, PDF'leri Python betikleri oluşturur
- Tam metin arama, yinelenen kayıt temizliği, e-posta doğrulama ve abonelikten çıkma desteği olan bir kişi veritabanı
- Iyzico ile satın alınan krediler, her kredi hareketinin kaydı ve admin panelinden iade
- Zamanlayıcı hazır e-postalarını her beş dakikada, takip e-postalarını her gün gönderir, kişileri her hafta doğrular ve eski kampanya dosyalarını 30 gün sonra temizler
- Kullanıcılar, kampanyalar, kişiler, ödemeler, destek talepleri ve anketler için admin alanı
- KVKK/GDPR için yasal sayfalar, veri dışa aktarma ve hesap silme

## Zorluklar
- Toplu e-posta gibi okunmayan tanıtım yazıları: her e-posta, çıkış analizinden ve kişinin kendi alanından üretilir; şablonlar bir insan yazmış gibi okunana kadar geliştirildi
- Ses analizi, video render ve PDF üretimi gibi ağır işler istek akışının dışında kalmalı ama sanatçıya yine de geri bildirim vermeli
- Temiz bir kişi listesi tutmak: yinelenen kayıtlar, hatalı adresler ve iletişim istemeyen kişiler

## Durum
Tuneout geliştirme aşamasındadır.
TXT;

        $now = now();

        DB::table('projects')->insert([
            'title' => 'Tuneout',
            'title_en' => 'Tuneout',
            'title_tr' => 'Tuneout',
            'slug' => 'tuneout',
            'summary' => 'A promotion tool for independent musicians, bands and labels that handles the research, writing and outreach of music promotion.',
            'summary_en' => 'A promotion tool for independent musicians, bands and labels that handles the research, writing and outreach of music promotion.',
            'summary_tr' => 'Bağımsız müzisyenler, gruplar ve plak şirketleri için müzik tanıtımının araştırma, yazma ve ulaşma işlerini üstlenen bir tanıtım aracı.',
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'featured' => false,
            'sort_order' => 20,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('slug', 'tuneout')->delete();
    }
};
