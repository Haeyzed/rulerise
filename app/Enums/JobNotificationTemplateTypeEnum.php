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
    case STATUS_SHORTLISTED = 'status_shortlisted';
    case STATUS_UNSORTED = 'status_unsorted';
    case STATUS_REJECTED = 'status_rejected';
    case STATUS_OFFER_SENT = 'status_offer_sent';
    case STATUS_HIRED = 'status_hired';
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
            self::STATUS_SHORTLISTED => 'Application Shortlisted',
            self::STATUS_UNSORTED => 'Application Under Review',
            self::STATUS_REJECTED => 'Application Rejected',
            self::STATUS_OFFER_SENT => 'Job Offer Sent',
            self::STATUS_HIRED => 'Candidate Hired',
            self::CUSTOM => 'Custom',
        };
    }

    /**
     * Map application status to corresponding template type
     *
     * @param string $status
     * @return JobNotificationTemplateTypeEnum
     */
    public static function fromApplicationStatus(string $status): JobNotificationTemplateTypeEnum
    {
        return match($status) {
            'shortlisted' => self::STATUS_SHORTLISTED,
            'unsorted' => self::STATUS_UNSORTED,
            'rejected' => self::STATUS_REJECTED,
            'offer_sent' => self::STATUS_OFFER_SENT,
            'hired' => self::STATUS_HIRED,
            default => self::CUSTOM,
        };
    }
}
