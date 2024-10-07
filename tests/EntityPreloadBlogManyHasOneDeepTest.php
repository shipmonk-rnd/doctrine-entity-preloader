<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

class EntityPreloadBlogManyHasOneDeepTest extends TestCase
{

    public function testManyHasOneDeepUnoptimized(): void
    {
        $this->createDummyBlogData(categoryCount: 5, categoryParentsCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->readArticleCategoryParentNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 10, 'query' => 'SELECT * FROM category t0 WHERE t0.id = ?'],
        ]);
    }

    public function testManyHasOneDeepWithFetchJoin(): void
    {
        $this->createDummyBlogData(categoryCount: 5, categoryParentsCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article', 'category', 'parentCategory')
            ->from(Article::class, 'article')
            ->leftJoin('article.category', 'category')
            ->leftJoin('category.parent', 'parentCategory')
            ->getQuery()
            ->getResult();

        $this->readArticleCategoryParentNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN category c1_ ON a0_.category_id = c1_.id LEFT JOIN category c2_ ON c1_.parent_id = c2_.id'],
        ]);
    }

    public function testManyHasOneDeepWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(categoryCount: 5, categoryParentsCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->setFetchMode(Article::class, 'category', ClassMetadata::FETCH_EAGER)
            ->setFetchMode(Category::class, 'parent', ClassMetadata::FETCH_EAGER) // this does not work
            ->getResult();

        $this->readArticleCategoryParentNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_'],
            ['count' => 1, 'query' => 'SELECT * FROM category t0 WHERE t0.id IN (?, ?, ?, ?, ?)'],
            ['count' => 5, 'query' => 'SELECT * FROM category t0 WHERE t0.id = ?'],
        ]);
    }

    public function testManyHasOneDeepWithPreload(): void
    {
        $this->createDummyBlogData(categoryCount: 5, categoryParentsCount: 5, articleInEachCategoryCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
        $categories = $this->getEntityPreloader()->preload($articles, 'category');
        $this->getEntityPreloader()->preload($categories, 'parent');

        $this->readArticleCategoryParentNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 2, 'query' => 'SELECT * FROM category c0_ WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Article> $articles
     */
    private function readArticleCategoryParentNames(array $articles): void
    {
        foreach ($articles as $article) {
            $article->getCategory()?->getParent()?->getName();
        }
    }

}
