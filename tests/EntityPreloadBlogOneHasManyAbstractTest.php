<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

class EntityPreloadBlogOneHasManyAbstractTest extends TestCase
{

    public function testOneHasManyAbstractUnoptimized(): void
    {
        $this->createDummyBlogData(categoryCount: 1, articleInEachCategoryCount: 5, commentForEachArticleCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->readComments($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 5, 'query' => 'SELECT * FROM comment t0 WHERE t0.article_id = ? ORDER BY t0.id DESC'],
            ['count' => 25, 'query' => 'SELECT * FROM contributor t0 WHERE t0.id = ? AND t0.dtype IN (?)'],
        ]);
    }

    public function testOneHasManyAbstractWithFetchJoin(): void
    {
        $this->createDummyBlogData(categoryCount: 1, articleInEachCategoryCount: 5, commentForEachArticleCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article', 'comment', 'author')
            ->from(Article::class, 'article')
            ->leftJoin('article.comments', 'comment')
            ->leftJoin('comment.author', 'author')
            ->getQuery()
            ->getResult();

        $this->readComments($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN comment c1_ ON a0_.id = c1_.article_id LEFT JOIN contributor c2_ ON c1_.author_id = c2_.id AND c2_.dtype IN (?) ORDER BY c1_.id DESC'],
        ]);
    }

    public function testOneHasManyAbstractWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(categoryCount: 1, articleInEachCategoryCount: 5, commentForEachArticleCount: 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->setFetchMode(Article::class, 'comments', ClassMetadata::FETCH_EAGER)
            ->setFetchMode(Comment::class, 'author', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readComments($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_'],
            ['count' => 1, 'query' => 'SELECT * FROM comment t0 WHERE t0.article_id IN (?, ?, ?, ?, ?) ORDER BY t0.id DESC'],
            ['count' => 25, 'query' => 'SELECT * FROM contributor t0 WHERE t0.id = ? AND t0.dtype IN (?)'],
        ]);
    }

    public function testOneHasManyAbstractWithPreload(): void
    {
        $this->createDummyBlogData(categoryCount: 1, articleInEachCategoryCount: 5, commentForEachArticleCount: 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
        $this->getEntityPreloader()->preload($articles, 'comments');

        $this->readComments($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM comment c0_ LEFT JOIN contributor c1_ ON c0_.author_id = c1_.id AND c1_.dtype IN (?) WHERE c0_.article_id IN (?, ?, ?, ?, ?) ORDER BY c0_.id DESC'],
        ]);
    }

    /**
     * @param array<Article> $articles
     */
    private function readComments(array $articles): void
    {
        foreach ($articles as $article) {
            foreach ($article->getComments() as $comment) {
                $comment->getContent();
            }
        }
    }

}
