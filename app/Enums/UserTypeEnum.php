<?php

namespace App\Enums;

/**
 * Class UserTypeEnum
 *
 * Represents different types of users in the system.
 *
 * @package App\Enums
 */
enum UserTypeEnum: string
{
    case ADMIN = 'admin';
    case CANDIDATE = 'candidate';
    case EMPLOYER = 'employer';
    case EMPLOYER_STAFF = 'employer_staff';

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
     * Get a human-readable label for the enum value.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::CANDIDATE => 'Candidate',
            self::EMPLOYER => 'Employer',
            self::EMPLOYER_STAFF => 'Employer Staff',
        };
    }

//    /**
//     * Get all enum values with their labels.
//     *
//     * @return array
//     */
//    public static function options(): array
//    {
//        return array_reduce(self::cases(), function ($carry, $enum) {
//            $carry[$enum->value] = $enum->label();
//            return $carry;
//        }, []);
//    }
}
