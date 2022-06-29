<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query\Expressions;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\Parameter\Parameter;
use YiiDb\DBAL\Query\Expressions\BaseExpressionBuilder;
use YiiDb\DBAL\Query\Expressions\Expression;
use YiiDb\DBAL\Query\Expressions\Operator;

use function is_array;
use function is_string;

final class ExpressionBuilder extends BaseExpressionBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?Connection $conn,
        public readonly ParameterBuilder $paramBuilder
    ) {
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function between(
        string|ExpressionInterface $column,
        mixed $minValue,
        mixed $maxValue,
        int|string|Type $valueType = null
    ): ExpressionInterface {
        $column = $this->prepareSafeColumn($column);
        $minValue = $this->prepareSafeValue($minValue, $valueType);
        $maxValue = $this->prepareSafeValue($maxValue, $valueType);

        return new Expression(
            "$column BETWEEN $minValue AND $maxValue",
            [...$column->getParams(), ...$minValue->getParams(), ...$maxValue->getParams()]
        );
    }

    /**
     * @param mixed|Parameter $value
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function comparison(
        string|ExpressionInterface $column,
        string $operator,
        mixed $value,
        int|string|Type $valueType = null
    ): Expression {
        if (is_string($column)) {
            $column = $this->wrap($column);

            if ($value instanceof ExpressionInterface) {
                return new Expression("$column $operator ($value)", $value->getParams());
            }

            $param = $this->paramBuilder->valueToExpr($value, $valueType);

            return new Expression("$column $operator $param", $param->getParams());
        }

        if ($value instanceof ExpressionInterface) {
            return new Expression(
                "($column) $operator ($value)",
                array_merge($column->getParams(), $value->getParams())
            );
        }

        $param = $this->paramBuilder->valueToExpr($value, $valueType);

        return new Expression(
            "($column) $operator $param",
            array_merge($column->getParams(), $param->getParams())
        );
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function eq(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::EQ, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function gt(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::GT, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function gte(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::GTE, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function in(
        string|ExpressionInterface $column,
        array|ExpressionInterface $values,
        int|string|Type $valueType = null,
        bool $not = false
    ): Expression {
        $operator = $not ? Operator::NOT_IN : Operator::IN;

        return is_array($values)
            ? $this->comparison($column, $operator, $this->paramBuilder->inValuesToExpr($values, $valueType))
            : $this->comparison($column, $operator, $values);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function lt(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::LT, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function lte(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::LTE, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function neq(string $column, mixed $value, int|string|Type $valueType = null): Expression
    {
        return $this->comparison($column, Operator::NEQ, $value, $valueType);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function notBetween(
        string|ExpressionInterface $column,
        mixed $minValue,
        mixed $maxValue,
        int|string|Type $valueType = null
    ): ExpressionInterface {
        $column = $this->prepareSafeColumn($column);
        $minValue = $this->prepareSafeValue($minValue, $valueType);
        $maxValue = $this->prepareSafeValue($maxValue, $valueType);

        return new Expression(
            "$column NOT BETWEEN $minValue AND $maxValue",
            [...$column->getParams(), ...$minValue->getParams(), ...$maxValue->getParams()]
        );
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function notIn(
        string|ExpressionInterface $column,
        array|ExpressionInterface $values,
        int|string|Type $valueType = null
    ): Expression {
        return $this->in($column, $values, $valueType, true);
    }

    /**
     * @param mixed|Parameter $value
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function value(mixed $value, int|string|Type $valueType = null): ExpressionInterface
    {
        return $this->paramBuilder->valueToExpr($value, $valueType);
    }

    public function wrap(string $value): string
    {
        return $this->conn?->wrap($value) ?? $value;
    }

    public function wrapSingle(string $value): string
    {
        return $this->conn?->wrapSingle($value) ?? $value;
    }

    public function wrapSql(string $sql): string
    {
        return $this->conn?->wrapSql($sql) ?? parent::wrapSql($sql);
    }

    private function prepareSafeColumn(string|ExpressionInterface $column): ExpressionInterface
    {
        return is_string($column)
            ? new Expression($this->wrap($column))
            : new Expression("($column)", $column->getParams());
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    private function prepareSafeValue(mixed $value, int|string|Type|null $valueType): ExpressionInterface
    {
        return $value instanceof ExpressionInterface
            ? new Expression("($value)", $value->getParams())
            : $this->paramBuilder->valueToExpr($value, $valueType);
    }
}
