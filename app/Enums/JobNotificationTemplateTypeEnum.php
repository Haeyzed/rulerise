<?php

namespace App\Enums;

/**
 * Enum JobNotificationTemplateTypeEnum
 * Defines types of job notification templates.
 */
enum JobNotificationTemplateTypeEnum: string
{
    case APPLICATION_RECEIVED = 'application_received';
    case INTERVIEW_INVITATION = 'interview_invitation';
    case REJECTION = 'rejection';
    case OFFER = 'offer';
    case CUSTOM = 'custom';

    /**
     * Get all enum values as an array.
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
