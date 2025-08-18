<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;

#[Entity]
class Article extends TestEntityWithCustomPrimaryKey
{

    #[Column]
    private string $title;

    #[Column]
    private string $content;

    #[ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    private ?Category $category;

    /**
     * @var Collection<int, Tag>
     */
    #[ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    private Collection $tags;

    /**
     * @var Collection<int, Comment>
     */
    #[OneToMany(targetEntity: Comment::class, mappedBy: 'article')]
    #[OrderBy(['id' => 'DESC'])]
    private Collection $comments;

    public function __construct(
        string $title,
        string $content,
        ?Category $category = null,
    )
    {
        parent::__construct();
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();

        $category?->addArticle($this);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * @return ReadableCollection<int, Tag>
     */
    public function getTags(): ReadableCollection
    {
        return $this->tags;
    }

    /**
     * @return ReadableCollection<int, Comment>
     */
    public function getComments(): ReadableCollection
    {
        return $this->comments;
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addArticle($this);
        }
    }

    /**
     * @internal
     */
    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

}
