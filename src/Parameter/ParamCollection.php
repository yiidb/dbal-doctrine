<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Parameter;

use DateTimeInterface;
use Doctrine\DBAL\Types\Type;

use function is_string;

/**
 * @internal
 */
final class ParamCollection
{
    private int $paramIndex = 0;
    private array $params = [];
    /**
     * @var array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    private array $types = [];

    public function addIndexValue(mixed $value): self
    {
        return $this->setValue($this->paramIndex++, $value);
    }

    public function addIndexValues(array $values): self
    {
        foreach ($values as $value) {
            $this->setValue($this->paramIndex++, $value);
        }

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @psalm-return array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function setValues(array $values): self
    {
        foreach ($values as $index => $value) {
            $this->setValue($index, $value);
        }

        return $this;
    }

    private function setValue(int|string $index, mixed $value): self
    {
        if (is_string($index)) {
            $index = ltrim($index, ':');
        }

        if ($value instanceof Parameter) {
            $this->params[$index] = $value->value;
            if ($value->type !== null) {
                $this->types[$index] = $value->type;
            }
        } elseif ($value instanceof DateTimeInterface) {
            $this->params[$index] = $value;
            $this->types[$index] = 'datetime';
        } else {
            $this->params[$index] = $value;
        }

        return $this;
    }
}
