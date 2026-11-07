<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RunLog's server was shut down, so its case study should not say "Live" or link to runlog.app.
 * Only touches text and the link that are still exactly as the original migration wrote them,
 * so anything edited later in Admin > Projects is left alone.
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

        $swap = fn (?string $text, string $from, string $to) => $text === null ? null : str_replace("\n$from\n", "\n$to\n", $text);

        $update = [
            'body' => $swap($project->body, 'Status: Live', 'Status: Archived, not currently hosted'),
            'body_en' => $swap($project->body_en, 'Status: Live', 'Status: Archived, not currently hosted'),
            'body_tr' => $swap($project->body_tr, 'Durum: Yayında', 'Durum: Arşivlendi, şu an yayında değil'),
            'updated_at' => now(),
        ];

        if ($project->live_url === 'https://runlog.app') {
            $update['live_url'] = null;
        }

        DB::table('projects')->where('id', $project->id)->update($update);
    }

    public function down(): void
    {
        //
    }
};
