<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\AcademicMention;
use App\Models\AcademicProgram;
use App\Models\Student;
use Illuminate\Database\Seeder;

class AcademicReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $levels = collect([
            ['code' => 'L1', 'name' => 'Licence 1', 'sort_order' => 1], ['code' => 'L2', 'name' => 'Licence 2', 'sort_order' => 2], ['code' => 'L3', 'name' => 'Licence 3', 'sort_order' => 3],
            ['code' => 'M1', 'name' => 'Master 1', 'sort_order' => 4], ['code' => 'M2', 'name' => 'Master 2', 'sort_order' => 5],
        ])->mapWithKeys(function ($data) {
            $level = AcademicLevel::withTrashed()->updateOrCreate(['code' => $data['code']], [...$data, 'is_active' => true]);
            $level->restore();

            return [$data['code'] => $level];
        });
        $mentions = collect([
            ['code' => 'DROIT', 'name' => 'Droit', 'description' => 'Mention Droit.'], ['code' => 'SCPO', 'name' => 'Sciences Politiques', 'description' => 'Mention Sciences Politiques.'],
        ])->mapWithKeys(function ($data) {
            $mention = AcademicMention::withTrashed()->updateOrCreate(['code' => $data['code']], [...$data, 'is_active' => true]);
            $mention->restore();

            return [$data['code'] => $mention];
        });
        $programs = [
            ['mention' => 'DROIT', 'code' => 'DROI', 'name' => 'Droit', 'levels' => ['L1', 'L2'], 'description' => 'Parcours Droit pour les niveaux L1 et L2.'],
            ['mention' => 'DROIT', 'code' => 'DPRI', 'name' => 'Droit Privé', 'levels' => ['L3'], 'description' => 'Parcours Droit Privé pour le niveau L3.'],
            ['mention' => 'DROIT', 'code' => 'DAFF', 'name' => 'Droit des Affaires', 'levels' => ['M1', 'M2'], 'description' => 'Parcours Droit des Affaires pour les niveaux M1 et M2.'],
            ['mention' => 'SCPO', 'code' => 'SCPO', 'name' => 'Science Politique', 'levels' => ['L1', 'L2', 'L3'], 'description' => 'Parcours Science Politique pour les niveaux L1, L2 et L3.'],
            ['mention' => 'SCPO', 'code' => 'ETPO', 'name' => 'Études Politiques', 'levels' => ['M1', 'M2'], 'description' => 'Parcours Études Politiques pour les niveaux M1 et M2.'],
        ];
        foreach ($programs as $data) {
            $program = AcademicProgram::withTrashed()->updateOrCreate(['code' => $data['code']], ['mention_id' => $mentions[$data['mention']]->id, 'name' => $data['name'], 'description' => $data['description'], 'is_active' => true]);
            $program->restore();
            $program->levels()->sync(collect($data['levels'])->map(fn ($code) => $levels[$code]->id));
        }

        Student::query()->whereNull('level_id')->each(function (Student $student) use ($levels) {
            $level = $levels->first(fn (AcademicLevel $item) => strcasecmp($item->name, (string) $student->level) === 0 || strcasecmp($item->code, (string) $student->level) === 0);
            $program = AcademicProgram::query()->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $student->program)])->first();
            if ($level || $program) {
                $student->update(['level_id' => $level?->id, 'program_id' => $program?->id, 'mention_id' => $program?->mention_id]);
            }
        });
    }
}
