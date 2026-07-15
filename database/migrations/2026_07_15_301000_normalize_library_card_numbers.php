<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $counters = [];
        DB::table('student_cards')->orderBy('issued_at')->orderBy('id')->get()->each(function ($card) use (&$counters) {
            $year = date('Y', strtotime($card->issued_at));
            $counters[$year] ??= (int) DB::table('student_cards')->where('card_number', 'like', 'BIB-'.substr($year, -2).'-%')->selectRaw("MAX(CAST(SUBSTRING_INDEX(card_number, '-', -1) AS UNSIGNED)) as value")->value('value');
            if (! str_starts_with($card->card_number, 'BIB-')) {
                $counters[$year]++;
                DB::table('student_cards')->where('id', $card->id)->update(['card_number' => sprintf('BIB-%s-%04d', substr($year, -2), $counters[$year])]);
            }
        });

        foreach ($counters as $year => $value) {
            DB::table('number_sequences')->updateOrInsert(
                ['key' => 'library_card', 'scope' => $year],
                ['current_value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void {}
};
