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
    case APPLICATION_WITHDRAWN = 'application_withdrawn';
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

    /**
     * Get human-readable name for the enum value.
     *
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::APPLICATION_RECEIVED => 'Application Received',
            self::INTERVIEW_INVITATION => 'Interview Invitation',
            self::REJECTION => 'Rejection',
            self::OFFER => 'Offer',
            self::APPLICATION_WITHDRAWN => 'Application Withdrawn',
            self::CUSTOM => 'Custom',
        };
    }
}
