<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

class EntityPreloadBlogManyHasOneTest extends TestCase
{

    public function testManyHasOneUnoptimized(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 5, 'query' => 'SELECT * FROM category t0 WHERE t0.id = ?'],
        ]);
    }

    public function testManyHasOneWithFetchJoin(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article', 'category')
            ->from(Article::class, 'article')
            ->leftJoin('article.category', 'category')
            ->getQuery()
            ->getResult();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN category c1_ ON a0_.category_id = c1_.id'],
        ]);
    }

    public function testManyHasOneWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->setFetchMode(Article::class, 'category', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_'],
            ['count' => 1, 'query' => 'SELECT * FROM category t0 WHERE t0.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    public function testManyHasOneWithPreload(): void
    {
        $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
        $this->getEntityPreloader()->preload($articles, 'category');

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Article> $articles
     */
    private function readArticleCategoryNames(array $articles): void
    {
        foreach ($articles as $article) {
            $article->getCategory()?->getName();
        }
    }

}
