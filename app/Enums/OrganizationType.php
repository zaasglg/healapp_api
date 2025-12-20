<?php

namespace App\Enums;

enum OrganizationType: string
{
    case AGENCY = 'agency';
    case BOARDING_HOUSE = 'boarding_house';

    public function label(): string
    {
        return match ($this) {
            self::AGENCY => 'Патронажное агентство',
            self::BOARDING_HOUSE => 'Пансионат',
        };
    }

    /**
     * Описание типа организации
     */
    public function description(): string
    {
        return match ($this) {
            self::AGENCY => 'Сотрудники привязываются к конкретным подопечным',
            self::BOARDING_HOUSE => 'Все сотрудники видят всех подопечных организации',
        };
    }

    /**
     * Проверить, требуется ли явное назначение доступа к дневникам
     */
    public function requiresExplicitAccess(): bool
    {
        return $this === self::AGENCY;
    }
}
