<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection as DoctrineConnection;
use Psr\Container\ContainerInterface;
use YiiDb\DBAL\Contracts\ConnectionManagerInterface;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\ConnectionManager;

/**
 * @var array $params
 */
return [
    ConnectionManagerInterface::class => ConnectionManager::class,
    ConnectionManager::class => [
        'definition' => static function (ContainerInterface $container) use ($params): ConnectionManager {
            /**
             * @var array{
             *     "yii/dbal": array{
             *         connectionManager: array{
             *             defaultConnection: string,
             *             connections: array<string, array{
             *                 params: array{path: string}
             *             }>
             *         },
             *         types: array{
             *             addTypes?: array<string, class-string>|null,
             *             overrideTypes?: array<string, class-string>|null
             *         }
             *     }
             * } $params
             *
             * For connectionManager->connections->params see psalm-type Params in {@see Doctrine\DBAL\DriverManager}
             */
            return new ConnectionManager(
                $container,
                $params['yii/dbal']['connectionManager'],
                $params['yii/dbal']['types']
            );
        },
        'reset' => function (): void {
            /**
             * @var ConnectionManagerInterface $this
             * @psalm-suppress InvalidScope
             */
            $this->resetConnections();
        },
    ],

    Connection::class => static fn (ConnectionManager $connManager): Connection => $connManager->getConnection(),

    DoctrineConnection::class => static fn (Connection $conn): DoctrineConnection => $conn->dConn,
];
