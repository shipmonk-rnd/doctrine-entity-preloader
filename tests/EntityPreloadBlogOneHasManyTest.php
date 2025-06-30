<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_map;

class EntityPreloadBlogOneHasManyTest extends TestCase
{

    public function testOneHasManyUnoptimized(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 5, 'query' => 'SELECT * FROM article t0 WHERE t0.category_id = ?'],
        ]);
    }

    public function testOneHasManyWithWithManualPreload(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

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

    public function testOneHasManyWithWithManualPreloadUsingPartial(): void
    {
        $this->skipIfPartialEntitiesAreNotSupported();
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $categories = $this->getEntityManager()->getRepository(Category::class)->findAll();
        $rawCategoryIds = array_map(
            static fn (Category $category): string => $category->getId()->getBytes(),
            $categories,
        );

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL category.{id}', 'article')
            ->from(Category::class, 'category')
            ->leftJoin('category.articles', 'article')
            ->where('category IN (:categories)')
            ->setParameter('categories', $rawCategoryIds, ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult();

        $this->readArticleTitles($categories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category t0'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN article a1_ ON c0_.id = a1_.category_id WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    public function testOneHasManyWithFetchJoin(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

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

    public function testOneHasManyWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

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

    public function testOneHasManyWithPreload(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

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
