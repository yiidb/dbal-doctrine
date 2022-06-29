<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine;

use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\Result as DoctrineResult;
use Traversable;
use YiiDb\DBAL\BaseResult;

final class Result extends BaseResult
{
    private ?int $columnCount = null;
    private ?int $rowCount = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly DoctrineResult $dResult
    ) {
    }

    /**
     * @throws DoctrineException
     */
    public function fetch(): ?array
    {
        $row = $this->dResult->fetchAssociative();

        return $row === false ? null : $row;
    }

    /**
     * @throws DoctrineException
     */
    public function fetchAll(): array
    {
        return $this->dResult->fetchAllAssociative();
    }

    /**
     * @throws DoctrineException
     */
    public function fetchAllNumeric(): array
    {
        return $this->dResult->fetchAllNumeric();
    }

    /**
     * @throws DoctrineException
     */
    public function fetchAllValues(): array
    {
        return $this->dResult->fetchFirstColumn();
    }

    /**
     * @throws DoctrineException
     */
    public function fetchNumeric(): ?array
    {
        $row = $this->dResult->fetchNumeric();

        return $row === false ? null : $row;
    }

    /**
     * @throws DoctrineException
     */
    public function fetchValue(): mixed
    {
        return $this->dResult->fetchOne();
    }

    public function free(): void
    {
        $this->dResult->free();
    }

    /**
     * @throws DoctrineException
     */
    public function getColumnCount(): int
    {
        return $this->columnCount ?? ($this->columnCount = $this->dResult->columnCount());
    }

    public function getDoctrineResult(): DoctrineResult
    {
        return $this->dResult;
    }

    /**
     * @throws DoctrineException
     */
    public function getRowCount(): int
    {
        return $this->rowCount ?? ($this->rowCount = $this->dResult->rowCount());
    }

    /**
     * @throws DoctrineException
     */
    public function iterate(): Traversable
    {
        return $this->dResult->iterateAssociative();
    }

    /**
     * @throws DoctrineException
     */
    public function iterateNumeric(): Traversable
    {
        return $this->dResult->iterateNumeric();
    }

    /**
     * @throws DoctrineException
     */
    public function iterateValues(): Traversable
    {
        return $this->dResult->iterateColumn();
    }
}
