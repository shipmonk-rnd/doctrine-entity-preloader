<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class Comment
{

    #[Id]
    #[Column]
    #[GeneratedValue]
    private int $id;

    #[ManyToOne(targetEntity: Article::class, inversedBy: 'comments')]
    private Article $article;

    #[ManyToOne(targetEntity: Contributor::class, inversedBy: 'comments')]
    private Contributor $author;

    #[Column]
    private string $content;

    public function __construct(Article $article, Contributor $author, string $content)
    {
        $this->article = $article;
        $this->author = $author;
        $this->content = $content;

        $article->addComment($this);
        $author->addComment($this);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getAuthor(): Contributor
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }

}
