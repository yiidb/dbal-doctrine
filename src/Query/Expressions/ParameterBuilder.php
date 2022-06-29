<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query\Expressions;

use Closure;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use LogicException;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Doctrine\Parameter\Parameter;
use YiiDb\DBAL\Query\Expressions\Expression;

use function array_filter;
use function array_unique;
use function implode;
use function is_string;
use function ltrim;

final class ParameterBuilder
{
    public bool $inlineParams;
    /**
     * Closure($this, mixed $value, int|string|Type|null $type, string|null $placeHolder): ExpressionInterface|null)
     *
     * @var null|Closure(self, mixed, int|string|Type|null, string|null):(ExpressionInterface|null)
     */
    public ?Closure $onCreateParameter = null;
    public readonly bool $useNamedParams;

    private int $boundCounter = 0;
    private readonly ?DoctrineConnection $dConn;
    private ?string $prefix = null;

    /**
     * @internal
     */
    public function __construct(?DoctrineConnection $dConn, bool $useNamedParams, bool $inlineParams)
    {
        $this->dConn = $dConn;
        $this->useNamedParams = $useNamedParams;
        $this->inlineParams = $inlineParams;
    }

    private static function paramToString(Parameter $param, DoctrineConnection $dConn): ?string
    {
        $value = $param->value;
        $type = $param->type;

        try {
            if (is_string($type)) {
                $type = Type::getType($type);
            }

            if ($type instanceof Type) {
                $value = $type->convertToDatabaseValue($value, $dConn->getDatabasePlatform());
                $bindingType = $type->getBindingType();
            } else {
                $bindingType = $type ?? ParameterType::STRING;
            }

            return match ($bindingType) {
                Parameter::TYPE_NONE,
                ParameterType::STRING => $dConn->quote((string)$value),
                ParameterType::INTEGER => (string)(int)$value,
                ParameterType::BOOLEAN => (string)$dConn->getDatabasePlatform()->convertBooleansToDatabaseValue($value),
                ParameterType::NULL => 'NULL',
                default => null
            };
        } catch (DoctrineException) {
            return null;
        }
    }

    /**
     * @param array<mixed|ExpressionInterface|Parameter> $values
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function inValuesToExpr(array $values, int|string|Type|null $valueType): Expression
    {
        $values = array_filter(array_unique($values));
        if (empty($values)) {
            throw new LogicException('The array of values for the IN operator cannot be empty.');
        }

        $names = [];
        $paramsArr = [];

        foreach ($values as $value) {
            if ($value instanceof ExpressionInterface) {
                $names[] = "($value)";
                $params = $value->getParams();
            } else {
                $param = $this->valueToExpr($value, $valueType);
                $names[] = (string)$param;
                $params = $param->getParams();
            }
            if (!empty($params)) {
                $paramsArr[] = $params;
            }
        }

        return new Expression(implode(',', $names), array_merge(...$paramsArr));
    }

    public function makeNamedPlaceHolder(): string
    {
        if (!isset($this->prefix)) {
            static $prefixCounter = 0;
            /** @psalm-var int $prefixCounter */
            $this->prefix = ':q' . dechex($prefixCounter++) . 'p';
        }

        return $this->prefix . ($this->boundCounter++);
    }

    /**
     * @return $this
     */
    public function setPrefix(?string $value): self
    {
        $this->prefix = ($value and $value[0] !== ':') ? ":$value" : $value;

        return $this;
    }

    /**
     * @param mixed|Parameter $value
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function valueToExpr(
        mixed $value,
        int|string|Type $valueType = null,
        string $placeHolder = null
    ): ExpressionInterface {
        if ($this->onCreateParameter) {
            $result = ($this->onCreateParameter)($this, $value, $valueType, $placeHolder);
            if ($result instanceof ExpressionInterface) {
                return $result;
            }
        }

        if ($value instanceof ExpressionInterface) {
            throw new LogicException('The ExpressionInterface value cannot be used as a parameter.');
        }

        $param = $value instanceof Parameter ? $value : new Parameter($value, $valueType, $this->inlineParams);

        if (($param->inline ?? $this->inlineParams) && $this->dConn !== null) {
            $expr = self::paramToString($param, $this->dConn);
            if (is_string($expr)) {
                return new Expression($expr);
            }
        }

        if ($this->useNamedParams) {
            $placeHolder = empty($placeHolder) ? $this->makeNamedPlaceHolder() : ':' . ltrim($placeHolder, ':');

            return new Expression($placeHolder, [$placeHolder => $param]);
        }

        return new Expression('?', [$param]);
    }
}
