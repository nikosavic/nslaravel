<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the RunSphere case study the same structure as the GameHub one (headings, lists, a stack fact).
 * The wording is unchanged. Skipped when the text already has headings, so a later edit in Admin > Projects
 * is never overwritten, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $project = DB::table('projects')->where('slug', 'runsphere-ticket-platform')->first();

        if (! $project || str_contains((string) $project->body_en, '## ') || str_contains((string) $project->body_tr, '## ')) {
            return;
        }

        $bodyEn = <<<'TXT'
Stack: Laravel, Tailwind CSS, MySQL, Iyzico

## Overview
RunSphere is a full-stack web platform designed to connect runners with race organizers through a centralized event discovery and management system. The platform allows runners to browse, search, and register for races, while enabling organizers to publish events and manage participants. Administrators oversee moderation, approvals, and platform operations.

RunSphere was built to solve a real problem: fragmented race listings, manual registration workflows, and lack of localized running platforms.

## Key features
### Public area
- Browse running events (5K, 10K, Half Marathon, Marathon, Trail)
- Advanced filtering by distance and terrain
- Event detail pages
- Automatic language detection (EN/TR)

### Authentication & roles
- Runner accounts
- Organizer accounts
- Admin dashboard
- Role-based permissions

### Organizer dashboard
- Create and edit events
- Upload race details
- Manage participant lists
- Track event status

### Admin panel
- Approve or reject submitted events
- Moderate content
- Manage users
- Platform overview

### Payments
- Integrated Iyzico payment gateway
- Secure checkout flow
- Transaction validation
- Order persistence

## Technical implementation
RunSphere was built using Laravel with a focus on clean architecture and real-world workflows.

### Core technical components
- Laravel MVC architecture
- Role-based authorization using middleware
- Locale-aware routing (/en /tr)
- Eloquent relationships
- Event approval pipeline
- Payment callbacks
- Tailwind CSS UI
- Alpine.js interactions
- Server-side validation
- SEO-friendly slugs

The system supports draft/published states, approval queues, and protected admin routes.

## Challenges & solutions
### Multi-role architecture
Different user types required distinct dashboards and permissions. This was solved through middleware-based access control and role-aware navigation.

### Localization
The platform supports both English and Turkish using URL-based localization, enabling automatic redirection and translated content fields.

### Payments
Iyzico integration required secure callback handling and transactional integrity. Payment states are persisted and verified server-side.

## Outcome
RunSphere demonstrates the ability to design and implement:
- Complex user workflows
- SaaS-style dashboards
- Payment systems
- Localization
- Admin moderation pipelines

It reflects a real production-level Laravel application rather than a demo project.
TXT;

        $bodyTr = <<<'TXT'
Teknoloji: Laravel, Tailwind CSS, MySQL, Iyzico

## Genel bakış
RunSphere, koşucular ile organizatörleri tek bir platformda buluşturan tam kapsamlı bir web uygulamasıdır. Koşucular yarışları keşfedebilir, filtreleyebilir ve kayıt olabilirken; organizatörler etkinlik oluşturabilir ve katılımcılarını yönetebilir. Admin paneli ise tüm içerik ve kullanıcıları denetler.

Platform, dağınık yarış listeleri ve manuel kayıt süreçleri sorunlarını çözmek amacıyla geliştirilmiştir.

## Temel özellikler
### Genel alan
- Yarış listeleme (5K, 10K, Yarı Maraton, Maraton, Trail)
- Mesafe ve zemin filtreleme
- Etkinlik detay sayfaları
- Otomatik dil algılama (EN/TR)

### Kullanıcı rolleri
- Koşucu hesapları
- Organizatör hesapları
- Admin paneli
- Rol bazlı yetkilendirme

### Organizatör paneli
- Etkinlik oluşturma/düzenleme
- Yarış detayları girme
- Katılımcı yönetimi
- Etkinlik durum takibi

### Admin paneli
- Etkinlik onaylama / reddetme
- Kullanıcı yönetimi
- İçerik moderasyonu
- Genel sistem kontrolü

### Ödeme sistemi
- Iyzico entegrasyonu
- Güvenli ödeme akışı
- Sunucu tarafı doğrulama
- Sipariş kayıtları

## Teknik yapı
RunSphere, gerçek hayattaki iş akışlarını yansıtacak şekilde Laravel kullanılarak geliştirildi.

### Başlıca teknik bileşenler
- Laravel MVC mimarisi
- Middleware ile rol bazlı erişim
- Dil bazlı routing (/en /tr)
- Eloquent ilişkileri
- Etkinlik onay süreci
- Ödeme callback yönetimi
- Tailwind CSS arayüzü
- Alpine.js etkileşimleri
- Sunucu tarafı validasyon
- SEO uyumlu URL yapısı

Sistem taslak/yayınlanmış durumları, onay kuyrukları ve korumalı admin alanları içerir.

## Karşılaşılan zorluklar
### Çoklu rol yapısı
Her kullanıcı tipi için ayrı arayüz ve yetkilendirme gereksinimi middleware tabanlı kontrol ile çözüldü.

### Çok dilli altyapı
URL bazlı lokalizasyon ile İngilizce ve Türkçe destek sağlandı.

### Ödeme entegrasyonu
Iyzico geri dönüşleri sunucu tarafında doğrulanarak işlem güvenliği sağlandı.

## Sonuç
RunSphere şu yetkinlikleri göstermektedir:
- Karmaşık kullanıcı akışları
- SaaS benzeri dashboard yapıları
- Ödeme sistemleri
- Lokalizasyon
- Admin onay süreçleri

Bu proje, demo değil üretim seviyesinde bir Laravel uygulamasıdır.
TXT;

        DB::table('projects')->where('id', $project->id)->update([
            'body' => $bodyEn,
            'body_en' => $bodyEn,
            'body_tr' => $bodyTr,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The original text was plain lines; nothing sensible to restore.
    }
};
