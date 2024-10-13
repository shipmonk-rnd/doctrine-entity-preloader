<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Lib;

use Composer\InstalledVersions;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Psr\Log\LoggerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use ShipMonk\DoctrineEntityPreloader\Exception\LogicException;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Bot;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Tag;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\User;
use Throwable;
use function unlink;
use function version_compare;

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
     * @param list<array{count: int, query: string}> $expectedQueries
     */
    protected function assertAggregatedQueries(
        array $expectedQueries,
        bool $omitSelectedColumns = true,
        bool $omitDiscriminatorConditions = true,
        bool $multiline = false,
    ): void
    {
        self::assertSame($expectedQueries, $this->getQueryLogger()->getAggregatedQueries(
            omitSelectedColumns: $omitSelectedColumns,
            omitDiscriminatorConditions: $omitDiscriminatorConditions,
            multiline: $multiline,
        ));
    }

    protected function createDummyBlogData(
        int $categoryCount = 1,
        int $categoryParentsCount = 0,
        int $articleInEachCategoryCount = 1,
        int $tagForEachArticleCount = 0,
        int $commentForEachArticleCount = 0,
        int $promptChangeCount = 0,
    ): void
    {
        $entityManager = $this->getEntityManager();

        for ($h = 0; $h < $categoryCount; $h++) {
            $categoryParent = null;

            for ($i = 0; $i < $categoryParentsCount; $i++) {
                $categoryParent = new Category("CategoryParent#{$i}", $categoryParent);
                $entityManager->persist($categoryParent);
            }

            $category = new Category("Category#{$h}", $categoryParent);
            $entityManager->persist($category);

            for ($i = 0; $i < $articleInEachCategoryCount; $i++) {
                $article = new Article("Article#{$i}", "Content of article #{$i}", $category);
                $entityManager->persist($article);

                for ($j = 0; $j < $tagForEachArticleCount; $j++) {
                    $tag = new Tag("Tag#{$j}");
                    $entityManager->persist($tag);
                    $article->addTag($tag);
                }

                for ($j = 0; $j < $commentForEachArticleCount; $j++) {
                    if ($j % 2 === 0) {
                        $contributor = new User("User#{$j}", "user{$j}@example.com", 'password');
                        $entityManager->persist($contributor);

                    } else {
                        $contributor = new Bot("Bot#{$i}", 'abcdef', prompt: 'You are a random user commenting on on article. Answer with just the comment text.');

                        for ($k = 0; $k < $promptChangeCount; $k++) {
                            $contributor->changePrompt("New prompt #{$k}");
                        }

                        $entityManager->persist($contributor);
                    }

                    $comment = new Comment($article, $contributor, "Comment #{$j}");
                    $entityManager->persist($comment);
                }
            }
        }

        $entityManager->flush();
        $entityManager->clear();
        $this->getQueryLogger()->clear();
    }

    /**
     * @param T $entity
     * @return T|null
     * @template T of object
     */
    protected function refreshEntity(object $entity): ?object
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
    protected function refreshExistingEntity(object $entity): object
    {
        $freshEntity = $this->refreshEntity($entity);

        if ($freshEntity === null) {
            $entityClass = $entity::class;
            throw new LogicException("Entity {$entityClass} was expected to exist even after refresh!");
        }

        return $freshEntity;
    }

    protected function skipIfPartialEntitiesAreNotSupported(): void
    {
        $ormVersion = InstalledVersions::getVersion('doctrine/orm') ?? '0.0.0';

        if (version_compare($ormVersion, '3.0.0', '>=') && version_compare($ormVersion, '3.3.0', '<')) {
            self::markTestSkipped('Partial entities are not supported in Doctrine ORM versions 3.0 to 3.2');
        }
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
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures'], isDevMode: true, proxyDir: __DIR__ . '/../../cache/proxies');
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        $config->setMiddlewares([new Middleware($logger)]);

        if ($inMemory) {
            $driverOptions = ['memory' => true];

        } else {
            $path = __DIR__ . '/../../cache/db.sqlite';
            $driverOptions = ['path' => $path];
            @unlink($path);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite'] + $driverOptions, $config);
        $entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $schemaValidator = new SchemaValidator($entityManager);
        $schemaValidator->validateMapping();

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
