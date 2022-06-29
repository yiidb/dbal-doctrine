<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\Types\Type;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use YiiDb\DBAL\Contracts\ConnectionManagerInterface;
use YiiDb\DBAL\Exceptions\InvalidArgumentException;

use function is_string;

/**
 * @psalm-import-type Params from DriverManager as ConnectionParams
 */
class ConnectionManager implements ConnectionManagerInterface
{
    private static bool $typesLoaded = false;
    /**
     * @var array<string, array{params: array}>
     */
    protected readonly array $connectionsParams;
    protected readonly ContainerInterface $container;
    protected readonly string $defaultConnection;
    /**
     * @var array<string, Connection>
     */
    private array $connections = [];

    /**
     * @param array{
     *     defaultConnection: string,
     *     connections: array<string, array{
     *         params: array
     *     }>
     * } $connManagerConfig
     * @psalm-param array{
     *     defaultConnection: string,
     *     connections: array<string, array{
     *         params: ConnectionParams
     *     }>
     * } $connManagerConfig
     * @param array{
     *     addTypes?: array<string, class-string>|null,
     *     overrideTypes?: array<string, class-string>|null
     * } $typesConfig
     *
     * @throws DoctrineException
     *
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#configuration
     */
    public function __construct(ContainerInterface $container, array $connManagerConfig, array $typesConfig = [])
    {
        $this->container = $container;

        $this->defaultConnection = $connManagerConfig['defaultConnection'] ?? 'default';
        $this->connectionsParams = $connManagerConfig['connections'] ?? [];

        if (!self::$typesLoaded) {
            self::$typesLoaded = true;
            self::loadTypes($typesConfig['addTypes'] ?? [], $typesConfig['overrideTypes'] ?? []);
        }
    }

    /**
     * @param iterable<string, class-string<Type>> $addTypes
     * @param iterable<string, class-string<Type>> $overrideTypes
     * @throws DoctrineException
     */
    private static function loadTypes(iterable $addTypes, iterable $overrideTypes): void
    {
        foreach ($addTypes as $name => $className) {
            Type::addType($name, $className);
        }
        foreach ($overrideTypes as $name => $className) {
            Type::overrideType($name, $className);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws DoctrineException
     * @throws NotFoundExceptionInterface
     */
    final public function getConnection(string $name = null): Connection
    {
        $name = $name ?? $this->defaultConnection;

        return $this->connections[$name] ?? ($this->connections[$name] = $this->makeConnection($name));
    }

    public function resetConnection(string $name): void
    {
        unset($this->connections[$name]);
    }

    public function resetConnections(): void
    {
        $this->connections = [];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws DoctrineException
     * @throws NotFoundExceptionInterface
     */
    protected function makeConnection(string $name): Connection
    {
        $connParams = $this->connectionsParams[$name] ?? null;

        if (!isset($connParams)) {
            throw new InvalidArgumentException(sprintf('Connection with name "%s" was not found.', $name));
        }

        return new Connection(
            dConn: $this->makeDoctrineConnection($connParams),
            useNamedParams: (bool)($connParams['useNamedParameters'] ?? false),
            inlineParams: (bool)($connParams['inlineParameters'] ?? false),
            realtimeCondBuilding: (bool)($connParams['realtimeConditionBuilding'] ?? false),
        );
    }

    /**
     * @param array{params: array} $connParams
     * @throws ContainerExceptionInterface
     * @throws DoctrineException
     * @throws NotFoundExceptionInterface
     */
    protected function makeDoctrineConnection(array $connParams): DoctrineConnection
    {
        $config = $this->makeDoctrineConnectionConfiguration($connParams);
        $eventManager = $this->makeDoctrineConnectionEventManager($connParams);

        /** @psalm-suppress MixedArgumentTypeCoercion */
        return DriverManager::getConnection($connParams['params'], $config, $eventManager);
    }

    /**
     * @param array<string, mixed> $connParams
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function makeDoctrineConnectionConfiguration(array $connParams, Configuration $conf = null): Configuration
    {
        $conf = $conf ?? new Configuration();

        /** @var string|LoggerInterface|null $queryLogger */
        if ($queryLogger = $connParams['queryLogger'] ?? null) {
            if (is_string($queryLogger)) {
                $queryLogger = $this->container->get($queryLogger);
            }
            if (!($queryLogger instanceof LoggerInterface)) {
                throw new LogicException('The "queryLogger" configuration parameter must contain '
                    . 'a class name or an object that supports the PSR-3 (Logger) interface.');
            }
            $conf->setMiddlewares([new LoggingMiddleware($queryLogger)]);
        }

        if ($queryCache = $connParams['queryCache'] ?? null) {
            if (is_string($queryCache)) {
                $queryCache = $this->container->get($queryCache);
            }
            if (!($queryCache instanceof CacheItemPoolInterface)) {
                throw new LogicException('The "queryCache" configuration parameter must contain '
                    . 'a class name or object that supports the PSR-6 interface.');
            }
            $conf->setResultCache($queryCache);
        }

        return $conf;
    }

    /**
     * @param array<string, mixed> $connParams
     *
     * @noinspection PhpUnusedParameterInspection
     */
    protected function makeDoctrineConnectionEventManager(array $connParams): EventManager
    {
        return new EventManager();
    }
}
