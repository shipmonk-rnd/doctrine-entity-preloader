<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Lib;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Psr\Log\LoggerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use ShipMonk\DoctrineEntityPreloader\Exception\LogicException;
use Throwable;
use function unlink;

abstract class TestCase extends PhpUnitTestCase
{

    private ?QueryLogger $queryLogger = null;

    private ?EntityManagerInterface $entityManager = null;

    /**
     * @var EntityPreloader<object>|null
     */
    private ?EntityPreloader $entityPreloader = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryLogger = null;
        $this->entityManager = null;
        $this->entityPreloader = null;
    }

    /**
     * @template T of Throwable
     * @param class-string<T> $type
     * @param callable(): mixed $cb
     * @param-immediately-invoked-callable $cb
     */
    protected static function assertException(string $type, ?string $message, callable $cb): void
    {
        try {
            $cb();
            self::fail("Expected exception of type {$type} to be thrown");

        } catch (Throwable $e) {
            self::assertInstanceOf($type, $e);

            if ($message !== null) {
                self::assertStringMatchesFormat($message, $e->getMessage());
            }
        }
    }

    /**
     * @param T $entity
     * @return T|null
     * @template T of object
     */
    public function refreshEntity(object $entity): ?object
    {
        if ($this->getEntityManager()->getUnitOfWork()->getEntityState($entity) === UnitOfWork::STATE_MANAGED) {
            throw new LogicException('Call $this->getEntityManager()->clear() before refreshing entity!');
        }

        $entityClass = $entity::class;
        $classMetadata = $this->getEntityManager()->getMetadataFactory()->getMetadataFor($entityClass);
        $entityIdentifiers = $classMetadata->getIdentifierValues($entity);

        return $this->getEntityManager()->find($entityClass, $entityIdentifiers);
    }

    /**
     * @param T $entity
     * @return T
     * @template T of object
     */
    public function refreshExistingEntity(object $entity): object
    {
        $freshEntity = $this->refreshEntity($entity);

        if ($freshEntity === null) {
            $entityClass = $entity::class;
            throw new LogicException("Entity {$entityClass} was expected to exist even after refresh!");
        }

        return $freshEntity;
    }

    protected function getQueryLogger(): QueryLogger
    {
        return $this->queryLogger ??= $this->createQueryLogger();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager ??= $this->createEntityManager($this->getQueryLogger());
    }

    /**
     * @return EntityPreloader<object>
     */
    protected function getEntityPreloader(): EntityPreloader
    {
        return $this->entityPreloader ??= $this->createEntityPreloader($this->getEntityManager());
    }

    private function createQueryLogger(): QueryLogger
    {
        return new QueryLogger();
    }

    private function createEntityManager(LoggerInterface $logger, bool $inMemory = true): EntityManagerInterface
    {
        $path = __DIR__ . '/../../cache/db.sqlite';
        @unlink($path);

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures'], isDevMode: true, proxyDir: __DIR__ . '/../../cache/proxies');
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        $config->setMiddlewares([new Middleware($logger)]);

        $driverOptions = $inMemory ? ['memory' => true] : ['path' => $path];
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite'] + $driverOptions, $config);
        $entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    /**
     * @return EntityPreloader<object>
     */
    private function createEntityPreloader(EntityManagerInterface $entityManager): EntityPreloader
    {
        return new EntityPreloader($entityManager);
    }

}
