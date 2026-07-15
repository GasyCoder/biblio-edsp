<?php

namespace App\Services;

use App\Models\AcademicLevel;
use App\Models\AcademicMention;
use App\Models\AcademicProgram;
use Illuminate\Validation\ValidationException;

class AcademicReferenceService
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function resolve(array $data): array
    {
        $level = isset($data['level_id']) ? AcademicLevel::find($data['level_id']) : $this->findLevel($data['level'] ?? null);
        $program = isset($data['program_id']) ? AcademicProgram::with('levels')->find($data['program_id']) : $this->findProgram($data['program'] ?? null);
        $mention = isset($data['mention_id']) ? AcademicMention::find($data['mention_id']) : ($this->findMention($data['mention'] ?? null) ?? $program?->mention);
        if ($program && $mention && $program->mention_id !== $mention->id) {
            throw ValidationException::withMessages(['program_id' => 'Ce parcours ne correspond pas à la mention sélectionnée.']);
        }
        if ($program && $level && ! $program->levels()->whereKey($level->id)->exists()) {
            throw ValidationException::withMessages(['level_id' => 'Ce niveau n’est pas autorisé pour le parcours sélectionné.']);
        }

        return [...$data, 'level_id' => $level?->id, 'mention_id' => $mention?->id, 'program_id' => $program?->id, 'level' => $level?->name ?? ($data['level'] ?? null), 'program' => $program?->name ?? ($data['program'] ?? null)];
    }

    public function tree(): array
    {
        return AcademicMention::query()->where('is_active', true)->with(['programs' => fn ($query) => $query->where('is_active', true)->with(['levels' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])])->orderBy('name')->get()->toArray();
    }

    private function findLevel(mixed $value): ?AcademicLevel
    {
        if (! $value) {
            return null;
        }

        return AcademicLevel::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])->orWhereRaw('LOWER(code) = ?', [mb_strtolower(trim((string) $value))])->first();
    }

    private function findProgram(mixed $value): ?AcademicProgram
    {
        if (! $value) {
            return null;
        }

        return AcademicProgram::query()->with('mention')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])->orWhereRaw('LOWER(code) = ?', [mb_strtolower(trim((string) $value))])->first();
    }

    private function findMention(mixed $value): ?AcademicMention
    {
        if (! $value) {
            return null;
        }

        return AcademicMention::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])->orWhereRaw('LOWER(code) = ?', [mb_strtolower(trim((string) $value))])->first();
    }
}
