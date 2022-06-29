<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use LogicException;

/**
 * Type that maps an SQL ENUM to a PHP string.
 */
final class EnumType extends StringType
{
    public const ENUM = 'enum';

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [self::ENUM];
    }

    public function getName(): string
    {
        return self::ENUM;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): never
    {
        throw new LogicException('This is the base type for all Enums. Use specialized types.');
    }
}
