<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Parameter;

use DateTimeInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

use function gettype;

/**
 * @psalm-immutable
 */
final class Parameter
{
    public const TYPE_AUTO = null;
    public const TYPE_NONE = -1;

    public readonly ?bool $inline;
    public readonly int|string|Type|null $type;
    public readonly mixed $value;

    /**
     * @psalm-param ParameterType::*|self::TYPE_*|string|Type|null $type
     *
     * @see ParameterType
     * @see Types
     */
    public function __construct(mixed $value, int|string|Type $type = null, bool $inline = null)
    {
        $this->value = $value;
        $this->type = match ($type) {
            self::TYPE_AUTO => $this->detectType($value),
            /** $type == null */
            self::TYPE_NONE => null,
            default => $type
        };
        $this->inline = $inline;
    }

    protected function detectType(mixed $value): int|string
    {
        return match (gettype($value)) {
            'boolean' => ParameterType::BOOLEAN,
            'integer' => ParameterType::INTEGER,
            'object' => $this->detectTypeByObject($value),
            'NULL' => ParameterType::NULL,
            default => ParameterType::STRING
        };
    }

    protected function detectTypeByObject(object $value): int|string
    {
        return $value instanceof DateTimeInterface ? 'datetime' : ParameterType::STRING;
    }
}
