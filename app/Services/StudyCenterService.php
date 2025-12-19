<?php

namespace App\Services;

use App\Models\StudyCenterRegistration;

class StudyCenterService
{
    /**
     * Get or create registration by chat_id
     */
    public function getOrCreateRegistration(int $chatId): StudyCenterRegistration
    {
        return StudyCenterRegistration::firstOrCreate(
            ['chat_id' => $chatId],
            ['state' => 'start']
        );
    }

    /**
     * Update registration state
     */
    public function updateState(StudyCenterRegistration $registration, string $state): void
    {
        $registration->update(['state' => $state]);
    }

    /**
     * Update full name
     */
    public function updateFullName(StudyCenterRegistration $registration, string $fullName): void
    {
        $registration->update([
            'full_name' => $fullName,
            'state' => 'subjects',
        ]);
    }

    /**
     * Update subjects
     */
    public function updateSubjects(StudyCenterRegistration $registration, string $subjects): void
    {
        $registration->update([
            'subjects' => $subjects,
            'state' => 'phone',
        ]);
    }

    /**
     * Update phone
     */
    public function updatePhone(StudyCenterRegistration $registration, string $phone): void
    {
        $registration->update([
            'phone' => $phone,
            'state' => 'completed',
        ]);
    }

    /**
     * Mark as subscribed
     */
    public function markAsSubscribed(StudyCenterRegistration $registration): void
    {
        $registration->update([
            'is_subscribed' => true,
            'state' => 'full_name',
        ]);
    }

    /**
     * Reset registration to start new registration
     */
    public function resetRegistration(StudyCenterRegistration $registration): void
    {
        $registration->update([
            'full_name' => null,
            'subjects' => null,
            'phone' => null,
            'is_subscribed' => false,
            'state' => 'start',
        ]);
    }
}

