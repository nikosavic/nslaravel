<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Race Timing case study to the Projects list. Inserts only when no project with this slug exists, so a
 * later edit in Admin > Projects is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || DB::table('projects')->where('slug', 'race-timing')->exists()) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel 12, Go, MySQL, Server-sent events, Tailwind CSS, Docker
Role: Full-stack development
Status: Working prototype, tested with a race simulator

## The problem
On race day, results often mean stopwatches, paper lists and a spreadsheet that someone fixes afterwards. Runners and their families want to know who is where while the race is still going. Race Timing records every checkpoint crossing as it happens and turns it into a live leaderboard.

## What it does
- Organisers create events and races, set the timing points (start, splits, finish) and import the participant list from a file
- Volunteers record runners with a phone: a printed QR label on each bib opens the timing page for that checkpoint with the bib number filled in, one tap records the crossing and an undo fixes a mistake
- A live leaderboard updates by itself while the race runs
- Every runner gets split times, a pace and a predicted finish time while still on the course
- Rankings overall, by gender, by age category, for runners who did not finish and for those who did not start, each with a CSV export
- A page for each runner
- A command marks runners who pass the cut-off time as did not finish
- A race simulator replays realistic reads for demos and tests

## How it is built
- Laravel runs the public site, the admin and the timing station pages
- A separate Go service takes in the timing reads, because every read has to be applied quickly and in the right order
- Each read goes through one function that updates the results in a transaction: it sets the start time, stores the split and, at the finish, sets the status, the elapsed time and the rank with MySQL window functions
- The Go service streams the leaderboard to the browser with server-sent events, so the page never has to poll
- Docker Compose runs MySQL, the web app, the timing service, an nginx front door and a scheduler
- A script seeds a demo event and replays a simulated race, so the whole chain can be tested without a real start line

## Challenges
- Reads that arrive out of order: a late mark from the middle of the course can reach the server after the finish, so late reads are ignored and a final result is never overwritten
- Predicting a finish while the runner is still on the course: the pace comes from the farthest split reached and is replaced by the real time at the finish
- Timing without special hardware: a QR label and a web page turn any phone into a timing station
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel 12, Go, MySQL, Server-sent events, Tailwind CSS, Docker
Rol: Full-stack geliştirme
Durum: Çalışan prototip, yarış simülatörüyle test edildi

## Sorun
Yarış gününde sonuçlar çoğu zaman kronometreler, kağıt listeler ve sonradan düzeltilen bir tablo demektir. Koşucular ve aileleri yarış sürerken kimin nerede olduğunu bilmek ister. Race Timing her kontrol noktası geçişini anında kaydeder ve canlı bir sıralamaya dönüştürür.

## Ne yapıyor
- Organizatörler etkinlik ve yarış oluşturur, zamanlama noktalarını (başlangıç, ara noktalar, bitiş) belirler ve katılımcı listesini bir dosyadan içe aktarır
- Gönüllüler koşucuları telefonla kaydeder: her göğüs numarasındaki basılı QR etiketi o kontrol noktasının zamanlama sayfasını numara dolu olarak açar, tek dokunuş geçişi kaydeder ve geri alma hatayı düzeltir
- Canlı sıralama yarış sürerken kendiliğinden güncellenir
- Her koşucu için parkurdayken ara süreler, tempo ve tahmini bitiş süresi hesaplanır
- Genel, cinsiyete göre, yaş kategorisine göre, bitirmeyenler ve başlamayanlar için sıralamalar; hepsinde CSV dışa aktarma
- Her koşucu için ayrı bir sayfa
- Bir komut, süre sınırını aşan koşucuları bitirmedi olarak işaretler
- Bir yarış simülatörü demolar ve testler için gerçekçi okumaları yeniden oynatır

## Nasıl yapıldı
- Laravel herkese açık siteyi, yönetim alanını ve zamanlama istasyonu sayfalarını çalıştırır
- Ayrı bir Go servisi zamanlama okumalarını alır, çünkü her okumanın hızlı ve doğru sırayla uygulanması gerekir
- Her okuma, sonuçları bir işlem içinde güncelleyen tek bir fonksiyondan geçer: başlangıç zamanını belirler, ara süreyi kaydeder ve bitişte durumu, geçen süreyi ve sırayı MySQL pencere fonksiyonlarıyla hesaplar
- Go servisi sıralamayı tarayıcıya server-sent events ile aktarır; sayfanın sürekli sorgulaması gerekmez
- Docker Compose; MySQL, web uygulaması, zamanlama servisi, bir nginx ön kapı ve bir zamanlayıcı çalıştırır
- Bir betik demo bir etkinlik oluşturur ve simüle edilmiş bir yarışı yeniden oynatır; böylece tüm zincir gerçek bir start çizgisi olmadan test edilir

## Zorluklar
- Sırasız gelen okumalar: parkurun ortasından geç bir işaret bitişten sonra sunucuya ulaşabilir; bu yüzden geç okumalar yok sayılır ve kesinleşmiş bir sonuç asla ezilmez
- Koşucu hâlâ parkurdayken bitiş tahmini: tempo ulaşılan en uzak ara noktadan alınır ve bitişte gerçek süreyle değiştirilir
- Özel donanım olmadan zamanlama: bir QR etiketi ve bir web sayfası her telefonu zamanlama istasyonuna çevirir
TXT;

        $now = now();

        DB::table('projects')->insert([
            'title' => 'Race Timing',
            'title_en' => 'Race Timing',
            'title_tr' => 'Race Timing',
            'slug' => 'race-timing',
            'summary' => 'Live race timing: phones record checkpoint crossings, a Go service builds the results and a live leaderboard updates while the race runs.',
            'summary_en' => 'Live race timing: phones record checkpoint crossings, a Go service builds the results and a live leaderboard updates while the race runs.',
            'summary_tr' => 'Canlı yarış zamanlaması: telefonlar kontrol noktası geçişlerini kaydeder, bir Go servisi sonuçları oluşturur ve canlı sıralama yarış sürerken güncellenir.',
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'featured' => false,
            'sort_order' => 40,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('slug', 'race-timing')->delete();
    }
};
