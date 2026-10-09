<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo'; // First In First Out
    case LIFO = 'lifo'; // Last In First Out
    case AVERAGE = 'average'; // Weighted Average
    case SPECIFIC = 'specific'; // Specific Identification

    public function label(): string
    {
        return match ($this) {
            self::FIFO => 'First-In, First-Out (FIFO)',
            self::LIFO => 'Last-In, First-Out (LIFO)',
            self::AVERAGE => 'Weighted Average Cost',
            self::SPECIFIC => 'Specific Identification',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FIFO => 'Items purchased first are sold first',
            self::LIFO => 'Items purchased last are sold first',
            self::AVERAGE => 'Cost is averaged across all inventory',
            self::SPECIFIC => 'Each item is tracked individually',
        };
    }
}
