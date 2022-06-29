<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query;

use BadMethodCallException;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use YiiDb\DBAL\Contracts\CommandTypeEnum;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Contracts\Query\InsertQueryInterface;
use YiiDb\DBAL\Contracts\Query\QueryTypeEnum;
use YiiDb\DBAL\Doctrine\Command;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\Parameter\Parameter;
use YiiDb\DBAL\Doctrine\Query\Expressions\ParameterBuilder;
use YiiDb\DBAL\Exceptions\ConnectionRequiredException;
use YiiDb\DBAL\Exceptions\InvalidArgumentException;
use YiiDb\DBAL\Query\BaseQuery;
use YiiDb\DBAL\Query\Expressions\Expression;
use YiiDb\DBAL\Query\SQLComposer;
use YiiDb\DBAL\Query\TableName;

use function array_key_exists;
use function count;
use function in_array;
use function is_string;

final class InsertQuery extends BaseQuery implements InsertQueryInterface
{
    public readonly ParameterBuilder $paramBuilder;
    /**
     * @var list<string>
     */
    private array $columns = [];
    private readonly ?Connection $conn;
    /**
     * @var array<Parameter|ExpressionInterface>[]
     */
    private array $rows = [];
    private string|TableName|ExpressionInterface $table;
    /**
     * @var array<Parameter|ExpressionInterface>
     */
    private array $values = [];

    /**
     * @internal
     */
    public function __construct(Query $query, string|TableName|ExpressionInterface $table)
    {
        $this->conn = $query->getConnection();
        $this->paramBuilder = $query->getParameterBuilder();
        $this->table = $table;
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function addRow(array $row, int|string|Type $valueType = null): static
    {
        if (!empty($this->values)) {
            throw new BadMethodCallException('The "row" method is not available when using values.');
        }

        $this->clearBuiltExpr();

        $this->rows[] = array_is_list($row)
            ? $this->prepareIndexedValues($row, $valueType)
            : $this->prepareNamedValues($row, $valueType);

        return $this;
    }

    public function columns(string ...$columns): static
    {
        if (!empty($this->values) || !empty($this->rows)) {
            throw new InvalidArgumentException('Column definitions are invalid when there are rows.');
        }

        $columns = array_values($columns);

        if (count($columns) === 1 && str_contains($columns[0], ',')) {
            $columns = array_map('trim', explode(',', $columns[0]));
        }

        if (count($columns) !== count(array_unique($columns))) {
            throw new InvalidArgumentException('Column names must be unique.');
        }

        $this->clearBuiltExpr();

        $this->columns = $columns;

        return $this;
    }

    public function createCommand(Connection $conn = null): Command
    {
        return new Command($this->toExpr(), $conn ?? $this->conn, CommandTypeEnum::Statement);
    }

    /**
     * @throws DoctrineException
     */
    public function execute(Connection $conn = null): int|string
    {
        $conn = $conn ?? $this->getRealConnection();
        $expr = $this->toExpr();

        return $conn->executeStatement((string)$expr, $expr->getParams());
    }

    public function getConnection(): ?Connection
    {
        return $this->conn;
    }

    public function getRealConnection(): Connection
    {
        return $this->getConnection() ?? throw new ConnectionRequiredException();
    }

    public function getType(): QueryTypeEnum
    {
        return QueryTypeEnum::Insert;
    }

    /**
     * @param array[] $rows
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function rows(array $rows, int|string|Type $valueType = null): static
    {
        $this->rows = [];
        foreach ($rows as $row) {
            $this->addRow($row, $valueType);
        }

        return $this;
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function setValue(string|int $column, mixed $value, int|string|Type $valueType = null): static
    {
        if (!empty($this->rows)) {
            throw new BadMethodCallException('The "setValue" method is not available when using rows.');
        }

        if (is_string($column)) {
            if (!empty($this->columns) && !in_array($column, $this->columns, true)) {
                throw new InvalidArgumentException(
                    sprintf('Column "%s" was not specified when calling the "columns" method.', $column)
                );
            }
        } else {
            if (!array_key_exists($column, $this->columns)) {
                throw new InvalidArgumentException(sprintf('Column with index %d is undefined.', $column));
            }

            $column = $this->columns[$column];
        }

        $this->clearBuiltExpr();

        $this->values[$column] = ($value instanceof Parameter) || ($value instanceof ExpressionInterface)
            ? $value
            : new Parameter($value, $valueType);

        return $this;
    }

    /**
     * @return $this
     */
    public function table(string|TableName|ExpressionInterface $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function values(array $values, int|string|Type $valueType = null): static
    {
        if (!empty($this->rows)) {
            throw new BadMethodCallException('The "values" method is not available when using rows.');
        }

        $this->clearBuiltExpr();

        $this->values = array_is_list($values)
            ? $this->prepareIndexedValues($values, $valueType)
            : $this->prepareNamedValues($values, $valueType);

        return $this;
    }

    protected function build(): Expression
    {
        $conn = $this->conn;

        $params = [];
        if (empty($this->rows)) {
            if (empty($this->values)) {
                $columns = [];
                $values = '';
            } else {
                $columns = array_keys($this->values);
                $values = $this->buildValues($this->values, $params);
            }
        } else {
            $columns = $this->columns;
            $rows = [];
            foreach ($this->rows as $values) {
                $rows[] = $this->buildValues($values, $params);
            }
            $values = implode('), (', $rows);
        }

        if ($conn !== null) {
            $columns = array_map(
                static fn (string|int $v): string => $conn->wrapSingle((string)$v),
                $columns
            );
        }
        $columns = implode(', ', $columns);
        $table = $this->buildTable($this->table);

        $composer = new SQLComposer($this->separator);

        $composer->add("INSERT INTO ($columns) $table");
        $composer->add("VALUES ($values)");
        $composer->addParams($params);
        $composer->addParams($this->getExtraParams());

        return $composer->toExpr();
    }

    /**
     * @param array<Parameter|ExpressionInterface> $values
     */
    private function buildValues(array $values, array &$params): string
    {
        $strings = [];
        $newParamsArr = [];
        foreach ($values as $value) {
            $expr = $value instanceof ExpressionInterface ? $value : $this->paramBuilder->valueToExpr($value);
            $strings[] = (string)$expr;
            if (!empty($exprParams = $expr->getParams())) {
                $newParamsArr[] = $exprParams;
            }
        }
        if (!empty($newParamsArr)) {
            $params = array_merge($params, ...$newParamsArr);
        }

        return implode(', ', $strings);
    }

    /**
     * @param list<mixed> $values
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     * @return array<Parameter|ExpressionInterface>
     */
    private function prepareIndexedValues(array $values, int|string|Type|null $valueType): array
    {
        if (!empty($values) && empty($this->columns)) {
            throw new InvalidArgumentException('First, you need to set up the list of columns.');
        }

        if (count($this->columns) !== count($values)) {
            throw new InvalidArgumentException(
                sprintf('%d columns passed, %d columns expected.', count($values), count($this->columns))
            );
        }

        foreach ($values as &$value) {
            $value = ($value instanceof Parameter) || ($value instanceof ExpressionInterface)
                ? $value
                : new Parameter($value, $valueType);
        }
        /** @psalm-var Parameter[] $values */

        return array_combine($this->columns, $values);
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     * @return array<Parameter|ExpressionInterface>
     */
    private function prepareNamedValues(array $values, int|string|Type|null $valueType): array
    {
        if (empty($this->columns)) {
            $this->columns = array_map(strval(...), array_keys($values));
        } elseif (array_keys($values) !== $this->columns) {
            throw new InvalidArgumentException(
                'Column names and order must match the columns specified by the columns method.'
            );
        }

        foreach ($values as &$value) {
            $value = ($value instanceof Parameter) || ($value instanceof ExpressionInterface)
                ? $value
                : new Parameter($value, $valueType);
        }
        /** @var array<Parameter|ExpressionInterface> $values */

        return $values;
    }
}
