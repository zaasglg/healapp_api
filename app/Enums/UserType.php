<?php

namespace App\Enums;

enum UserType: string
{
    case ORGANIZATION = 'organization';
    case PRIVATE_CAREGIVER = 'private_caregiver';
    case CLIENT = 'client';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIZATION => 'Организация',
            self::PRIVATE_CAREGIVER => 'Частная сиделка',
            self::CLIENT => 'Клиент',
        };
    }

    /**
     * Проверить, может ли тип пользователя владеть организацией
     */
    public function canOwnOrganization(): bool
    {
        return $this === self::ORGANIZATION;
    }

    /**
     * Проверить, может ли тип пользователя работать без организации
     */
    public function worksIndependently(): bool
    {
        return $this === self::PRIVATE_CAREGIVER;
    }
}
