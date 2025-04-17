<?php

namespace App\Enums;

enum BlogPostStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    /**
     * Get all values as an array.
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the label for the enum value.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Get the color for the enum value.
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => '#ffc107', // Yellow
            self::PUBLISHED => '#28a745', // Green
            self::ARCHIVED => '#6c757d', // Gray
        };
    }
}
