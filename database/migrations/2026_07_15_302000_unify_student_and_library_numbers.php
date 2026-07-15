<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $students = DB::table('students')->orderBy('id')->get(['id', 'registration_number', 'created_at']);
            $cards = DB::table('student_cards')->orderBy('id')->get(['id', 'student_id', 'status']);

            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->update(['registration_number' => 'TMP-STUDENT-'.$student->id]);
            }
            foreach ($cards as $card) {
                DB::table('student_cards')->where('id', $card->id)->update(['card_number' => 'TMP-CARD-'.$card->id]);
            }

            $counters = [];
            $numbers = [];
            foreach ($students as $student) {
                preg_match('/(?:ETU|BIB)-(\d{2}|\d{4})-(\d+)$/', (string) $student->registration_number, $matches);
                $year = isset($matches[1])
                    ? (strlen($matches[1]) === 2 ? '20'.$matches[1] : $matches[1])
                    : substr((string) ($student->created_at ?: now()->format('Y')), 0, 4);
                $year = preg_match('/^\d{4}$/', $year) ? $year : now()->format('Y');
                $counters[$year] = ($counters[$year] ?? 0) + 1;
                $numbers[$student->id] = sprintf('BIB-%s-%03d', substr($year, -2), $counters[$year]);
                DB::table('students')->where('id', $student->id)->update(['registration_number' => $numbers[$student->id]]);
            }

            foreach ($cards as $card) {
                $number = $numbers[$card->student_id] ?? 'BIB-ARCHIVE-'.$card->id;
                DB::table('student_cards')->where('id', $card->id)->update([
                    'card_number' => $card->status === 'active' ? $number : 'ARCHIVE-'.$card->id.'-'.$number,
                ]);
            }

            foreach ($counters as $year => $value) {
                DB::table('number_sequences')->updateOrInsert(
                    ['key' => 'student', 'scope' => $year],
                    ['current_value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    public function down(): void
    {
        // Les anciens numéros ETU et les numéros de cartes séparés ne peuvent pas être reconstruits sans ambiguïté.
    }
};
