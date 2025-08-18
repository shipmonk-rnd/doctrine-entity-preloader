<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_map;

class EntityPreloadBlogOneHasManyTest extends TestCase
{

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyUnoptimized(DbalType $primaryKey): void
    {
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 5, 'query' => 'SELECT * FROM article t0 WHERE t0.category_id = ?'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyWithWithManualPreload(DbalType $primaryKey): void
    {
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();

        $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->where('article.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->getQuery()
            ->getResult();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ WHERE a0_.category_id IN (?, ?, ?, ?, ?)'],
            ['count' => 5, 'query' => 'SELECT * FROM article t0 WHERE t0.category_id = ?'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyWithWithManualPreloadUsingPartial(DbalType $primaryKey): void
    {
        $this->skipIfPartialEntitiesAreNotSupported();
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
        $rawCategoryIds = array_map(
            static fn (Category $category) => $primaryKey->convertToDatabaseValue($category->getId(), $platform),
            $categories,
        );

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL category.{id}', 'article')
            ->from(Category::class, 'category')
            ->leftJoin('category.articles', 'article')
            ->where('category IN (:categories)')
            ->setParameter('categories', $rawCategoryIds, $this->deduceArrayParameterType($primaryKey))
            ->getQuery()
            ->getResult();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN article a1_ ON c0_.id = a1_.category_id WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyWithFetchJoin(DbalType $primaryKey): void
    {
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->createQueryBuilder()
            ->select('category', 'article')
            ->from(Category::class, 'category')
            ->leftJoin('category.articles', 'article')
            ->getQuery()
            ->getResult();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN article a1_ ON c0_.id = a1_.category_id'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyWithEagerFetchMode(DbalType $primaryKey): void
    {
        $this->skipIfDoctrineOrmHasBrokenUnhandledMatchCase();
        $this->skipIfDoctrineOrmHasBrokenEagerFetch($primaryKey); // here the test it green, but emits PHP warning
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->getQuery()
            ->setFetchMode(Category::class, 'articles', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_'],
            ['count' => 1, 'query' => 'SELECT * FROM article t0 WHERE t0.category_id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyWithPreload(DbalType $primaryKey): void
    {
        $this->createDummyBlogData($primaryKey, categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();
        $this->getEntityPreloader()->preload($categories, 'articles');

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ WHERE a0_.category_id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Category> $categories
     */
    private function readArticleTitles(array $categories): void
    {
        foreach ($categories as $category) {
            $category->getName();

            foreach ($category->getArticles() as $article) {
                $article->getTitle();
            }
        }
    }

}
