<?php

namespace App\Enums;

enum PagePhotoCollection: string
{
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Gallery => 'Gallery',
        };
    }

    public function maxCount(): int
    {
        return match ($this) {
            self::Gallery => 24,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
