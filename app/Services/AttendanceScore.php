<?php

namespace App\Services;

/**
 * Règle unique du score d'assiduité, partagée par le rapport global
 * (AttendanceReportController) et la fiche étudiant (StudentController).
 */
class AttendanceScore
{
    public const PRESENCE = 2;

    public const CONSULTATION = 1;

    public const LOAN = 3;

    public static function compute(int $daysPresent, int $consultations, int $loans): int
    {
        return $daysPresent * self::PRESENCE
            + $consultations * self::CONSULTATION
            + $loans * self::LOAN;
    }

    /** @return array{presence: int, consultation: int, loan: int} */
    public static function weights(): array
    {
        return ['presence' => self::PRESENCE, 'consultation' => self::CONSULTATION, 'loan' => self::LOAN];
    }
}
