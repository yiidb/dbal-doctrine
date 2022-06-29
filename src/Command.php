<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine;

use YiiDb\DBAL\BaseCommand;
use YiiDb\DBAL\Contracts\CommandTypeEnum;
use YiiDb\DBAL\Contracts\ConnectionInterface;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Exceptions\InvalidArgumentTypeException;

/**
 * @extends BaseCommand<Connection>
 * @method Result executeQuery()
 * @method Connection|null getConnection()
 * @method Connection getRealConnection()
 */
final class Command extends BaseCommand
{
    public function __construct(
        string|ExpressionInterface $sql = '',
        Connection $conn = null,
        CommandTypeEnum $type = CommandTypeEnum::Unknown
    ) {
        parent::__construct($sql, $conn, $type);
    }

    public function withConnection(ConnectionInterface $conn = null): static
    {
        if ($conn !== null && !($conn instanceof Connection)) {
            throw new InvalidArgumentTypeException('$conn', [Connection::class, 'null'], $conn);
        }

        return parent::withConnection($conn);
    }
}
