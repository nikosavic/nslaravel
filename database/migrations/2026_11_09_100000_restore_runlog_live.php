<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RunLog is hosted again at https://runlog.app, so its case study says "Live" and links there again, and the
 * feature list mentions the public runner page. Each change only touches text that is still exactly as the earlier
 * migrations wrote it, so anything edited later in Admin > Projects is left alone, and it is safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $project = DB::table('projects')->where('slug', 'runlog')->first();

        if (! $project) {
            return;
        }

        $swap = fn (?string $text, string $from, string $to) => $text === null ? null : str_replace($from, $to, $text);

        $statusEn = fn (?string $t) => $swap($t, "\nStatus: Archived, not currently hosted\n", "\nStatus: Live\n");
        $statusTr = fn (?string $t) => $swap($t, "\nDurum: Arşivlendi, şu an yayında değil\n", "\nDurum: Yayında\n");

        $shareEn = '- Makes a share card for any run';
        $shareTr = '- Her koşu için bir paylaşım kartı üretir';

        $addEn = $shareEn."\n"
            ."- Gives each runner an optional public page with weekly totals, a year heatmap, personal bests, streaks and a race countdown, with a preview image for sharing\n"
            .'- Draws the route of an outdoor run on a map, with the start and the end trimmed so a home address is never shown';
        $addTr = $shareTr."\n"
            ."- Her koşucuya isteğe bağlı bir herkese açık sayfa verir: haftalık toplamlar, yıllık ısı haritası, kişisel rekorlar, seriler ve yarış geri sayımı; paylaşım için önizleme görseliyle\n"
            .'- Açık havada yapılan koşunun rotasını haritada çizer; başlangıç ve bitiş kırpılır, böylece ev adresi hiçbir zaman görünmez';

        $en = fn (?string $t) => $swap($statusEn($t), $shareEn."\n- Sells", $addEn."\n- Sells");
        $tr = fn (?string $t) => $swap($statusTr($t), $shareTr."\n- Iyzico", $addTr."\n- Iyzico");

        $update = [
            'body' => $en($project->body),
            'body_en' => $en($project->body_en),
            'body_tr' => $tr($project->body_tr),
            'updated_at' => now(),
        ];

        if (empty($project->live_url)) {
            $update['live_url'] = 'https://runlog.app';
        }

        DB::table('projects')->where('id', $project->id)->update($update);
    }

    public function down(): void
    {
        //
    }
};
