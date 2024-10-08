<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Tag;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

class EntityPreloadBlogManyHasManyInversedTest extends TestCase
{

    public function testManyHasManyInversedUnoptimized(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $tags = $this->getEntityManager()->getRepository(Tag::class)->findAll();

        $this->readArticleTitles($tags);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM tag t0'],
            ['count' => 25, 'query' => 'SELECT * FROM article t0 INNER JOIN article_tag ON t0.id = article_tag.article_id WHERE article_tag.tag_id = ?'],
        ]);
    }

    public function testManyHasManyInversedWithFetchJoin(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $tags = $this->getEntityManager()->createQueryBuilder()
            ->select('tag', 'article')
            ->from(Tag::class, 'tag')
            ->leftJoin('tag.articles', 'article')
            ->getQuery()
            ->getResult();

        $this->readArticleTitles($tags);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM tag t0_ LEFT JOIN article_tag a2_ ON t0_.id = a2_.tag_id LEFT JOIN article a1_ ON a1_.id = a2_.article_id'],
        ]);
    }

    public function testManyHasManyInversedWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        // for eagerly loaded Many-To-Many associations one query has to be made for each collection
        // https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/working-with-objects.html#by-eager-loading
        $tags = $this->getEntityManager()->createQueryBuilder()
            ->select('tag')
            ->from(Tag::class, 'tag')
            ->getQuery()
            ->setFetchMode(Tag::class, 'articles', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readArticleTitles($tags);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM tag t0_'],
            ['count' => 25, 'query' => 'SELECT * FROM article t0 INNER JOIN article_tag ON t0.id = article_tag.article_id WHERE article_tag.tag_id = ?'],
        ]);
    }

    public function testManyHasManyInversedWithPreload(): void
    {
        $this->createDummyBlogData(articleInEachCategoryCount: 5, tagForEachArticleCount: 5);

        $tags = $this->getEntityManager()->getRepository(Tag::class)->findAll();
        $this->getEntityPreloader()->preload($tags, 'articles');

        $this->readArticleTitles($tags);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM tag t0'],
            ['count' => 1, 'query' => 'SELECT * FROM tag t0_ INNER JOIN article_tag a2_ ON t0_.id = a2_.tag_id INNER JOIN article a1_ ON a1_.id = a2_.article_id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ WHERE a0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Tag> $tags
     */
    private function readArticleTitles(array $tags): void
    {
        foreach ($tags as $tag) {
            foreach ($tag->getArticles() as $article) {
                $article->getTitle();
            }
        }
    }

}
