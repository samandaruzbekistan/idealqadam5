<?php

namespace App\Services;

use App\Models\Registration;

class RegistrationService
{
    /**
     * Get or create registration by chat_id
     */
    public function getOrCreateRegistration(int $chatId): Registration
    {
        return Registration::firstOrCreate(
            ['chat_id' => $chatId],
            ['state' => 'start']
        );
    }

    /**
     * Get available subjects based on grade
     */
    public function getSubjectsByGrade(int $grade): array
    {
        if ($grade >= 1 && $grade <= 4) {
            // Automatically assigned subjects
            return ['Matematika', 'Ingliz tili', 'Mantiq'];
        } elseif ($grade >= 5 && $grade <= 6) {
            // Two options
            return [
                'Tabiiy fan - Ingliz tili',
                'Matematika - Ingliz tili',
            ];
        } elseif ($grade >= 7 && $grade <= 10) {
            // Four options
            return [
                'Matematika - Fizika',
                'Matematika - Ingliz tili',
                'Biologiya - Kimyo',
                'Huquq - Ingliz tili',
            ];
        }

        return [];
    }

    /**
     * Check if grade should skip subject selection
     */
    public function shouldSkipSubjectSelection(int $grade): bool
    {
        return $grade >= 1 && $grade <= 4;
    }

    /**
     * Update registration state
     */
    public function updateState(Registration $registration, string $state): void
    {
        $registration->update(['state' => $state]);
    }

    /**
     * Update full name
     */
    public function updateFullName(Registration $registration, string $fullName): void
    {
        $registration->update([
            'full_name' => $fullName,
            'state' => 'school',
        ]);
    }

    /**
     * Update school name
     */
    public function updateSchool(Registration $registration, string $school): void
    {
        $registration->update([
            'school' => $school,
            'state' => 'grade',
        ]);
    }

    /**
     * Update grade
     */
    public function updateGrade(Registration $registration, int $grade): void
    {
        $registration->update(['grade' => $grade]);

        if ($this->shouldSkipSubjectSelection($grade)) {
            // Auto-assign subjects for grades 1-4
            $subjects = $this->getSubjectsByGrade($grade);
            $registration->update([
                'subjects' => implode(', ', $subjects),
                'state' => 'subscription',
            ]);
        } else {
            $registration->update(['state' => 'subjects']);
        }
    }

    /**
     * Update subjects
     */
    public function updateSubjects(Registration $registration, string $subjects): void
    {
        $registration->update([
            'subjects' => $subjects,
            'state' => 'subscription',
        ]);
    }

    /**
     * Mark as subscribed and complete registration
     */
    public function completeRegistration(Registration $registration): void
    {
        $registration->update([
            'is_subscribed' => true,
            'state' => 'completed',
        ]);
    }

    /**
     * Validate grade input
     */
    public function isValidGrade(string $input): bool
    {
        $grade = (int) $input;
        return $grade >= 1 && $grade <= 10;
    }

    /**
     * Validate subject selection
     */
    public function isValidSubject(int $grade, string $subject): bool
    {
        $availableSubjects = $this->getSubjectsByGrade($grade);
        return in_array($subject, $availableSubjects);
    }
}

