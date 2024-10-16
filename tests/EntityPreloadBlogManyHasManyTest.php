<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

class EntityPreloadBlogManyHasManyTest extends TestCase
{

    public function testManyHasManyUnoptimized(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->readTagLabels($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 5, 'query' => 'SELECT * FROM tag t0 INNER JOIN article_tag ON t0.id = article_tag.tag_id WHERE article_tag.article_id = ?'],
        ]);
    }

    public function testOneHasManyWithWithManualPreloadUsingPartial(): void
    {
        $this->skipIfPartialEntitiesAreNotSupported();
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL article.{id}', 'tag')
            ->from(Article::class, 'article')
            ->leftJoin('article.tags', 'tag')
            ->where('article IN (:articles)')
            ->setParameter('articles', $articles)
            ->getQuery()
            ->getResult();

        $this->readTagLabels($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN article_tag a2_ ON a0_.id = a2_.article_id LEFT JOIN tag t1_ ON t1_.id = a2_.tag_id WHERE a0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    public function testManyHasManyWithFetchJoin(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article', 'tag')
            ->from(Article::class, 'article')
            ->leftJoin('article.tags', 'tag')
            ->getQuery()
            ->getResult();

        $this->readTagLabels($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN article_tag a2_ ON a0_.id = a2_.article_id LEFT JOIN tag t1_ ON t1_.id = a2_.tag_id'],
        ]);
    }

    public function testManyHasManyWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        // for eagerly loaded Many-To-Many associations one query has to be made for each collection
        // https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/working-with-objects.html#by-eager-loading
        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->setFetchMode(Article::class, 'tags', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readTagLabels($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_'],
            ['count' => 5, 'query' => 'SELECT * FROM tag t0 INNER JOIN article_tag ON t0.id = article_tag.tag_id WHERE article_tag.article_id = ?'],
        ]);
    }

    public function testManyHasManyWithPreload(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
        $this->getEntityPreloader()->preload($articles, 'tags');

        $this->readTagLabels($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ INNER JOIN article_tag a2_ ON a0_.id = a2_.article_id INNER JOIN tag t1_ ON t1_.id = a2_.tag_id WHERE a0_.id IN (?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM tag t0_ WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Article> $articles
     */
    private function readTagLabels(array $articles): void
    {
        foreach ($articles as $article) {
            foreach ($article->getTags() as $tag) {
                $tag->getLabel();
            }
        }
    }

}
