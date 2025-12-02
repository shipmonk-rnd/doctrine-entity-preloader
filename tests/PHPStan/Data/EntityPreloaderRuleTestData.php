<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\PHPStan\Data;

use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Bot;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\BotPromptVersion;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Contributor;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\PasswordVerifier;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Tag;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\User;
use function PHPStan\Testing\assertType;

final class EntityPreloaderRuleTestData
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EntityPreloader $entityPreloader,
    )
    {
    }

    public function preloadOneHasMany(): void
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article>', $this->entityPreloader->preload($categories, 'articles'));
        assertType('list<object>', $this->entityPreloader->preload($categories, 'notFound')); // error: Property 'ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category::$notFound' not found.
        assertType('list<object>', $this->entityPreloader->preload($categories, 'name')); // error: Property 'ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category::$name' is not a valid Doctrine association.
        assertType('list<object>', $this->entityPreloader->preload($categories, 'id')); // error: Property 'ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\TestEntityWithCustomPrimaryKey::$id' is not a valid Doctrine association.

        $bots = $this->entityManager->getRepository(Bot::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment>', $this->entityPreloader->preload($bots, 'comments'));
    }

    public function preloadOneHasManySelfReferencing(): void
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category>', $this->entityPreloader->preload($categories, 'children'));

        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment>', $this->entityPreloader->preload($articles, 'comments'));
    }

    public function preloadManyHasOne(): void
    {
        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category>', $this->entityPreloader->preload($articles, 'category'));

        $comments = $this->entityManager->getRepository(Comment::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article>', $this->entityPreloader->preload($comments, 'article'));
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Contributor>', $this->entityPreloader->preload($comments, 'author'));

        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category>', $this->entityPreloader->preload($categories, 'parent'));
    }

    public function preloadManyHasMany(): void
    {
        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Tag>', $this->entityPreloader->preload($articles, 'tags'));

        $tags = $this->entityManager->getRepository(Tag::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article>', $this->entityPreloader->preload($tags, 'articles'));
    }

    public function preloadOneHasOne(): void
    {
        $bots = $this->entityManager->getRepository(Bot::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\BotPromptVersion>', $this->entityPreloader->preload($bots, 'activePrompt'));

        $botPromptVersions = $this->entityManager->getRepository(BotPromptVersion::class)->findAll();
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\BotPromptVersion>', $this->entityPreloader->preload($botPromptVersions, 'prevVersion'));
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\BotPromptVersion>', $this->entityPreloader->preload($botPromptVersions, 'nextVersion'));
    }

    public function preloadWithUnionTypes(): void
    {
        $bots = $this->entityManager->getRepository(Bot::class)->findAll();
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $union = [...$bots, ...$users];
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment>', $this->entityPreloader->preload($union, 'comments'));

        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        $comments = $this->entityManager->getRepository(Comment::class)->findAll();
        $union = [...$articles, ...$comments];
        assertType('list<object>', $this->entityPreloader->preload($union, 'foobar')); // error: Property 'ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article::$foobar' not found.
        assertType('list<object>', $this->entityPreloader->preload($union, 'tags')); // error: Property 'ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment::$tags' not found.
    }

    /**
     * @param list<Contributor & PasswordVerifier> $entities
     */
    public function preloadWithInteractionTypes(array $entities): void
    {
        assertType('list<ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Comment>', $this->entityPreloader->preload($entities, 'comments'));
    }

}
