<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class Category
{

    #[Id]
    #[Column]
    #[GeneratedValue]
    private int $id;

    #[Column]
    private string $name;

    #[ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private ?self $parent;

    /**
     * @var Collection<int, Category>
     */
    #[OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    /**
     * @var Collection<int, Article>
     */
    #[OneToMany(targetEntity: Article::class, mappedBy: 'category')]
    private Collection $articles;

    public function __construct(string $name, ?self $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
        $this->articles = new ArrayCollection();

        $parent?->addChild($this);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return ReadableCollection<int, Category>
     */
    public function getChildren(): ReadableCollection
    {
        return $this->children;
    }

    /**
     * @return ReadableCollection<int, Article>
     */
    public function getArticles(): ReadableCollection
    {
        return $this->articles;
    }

    /**
     * @internal
     */
    public function addChild(Category $category): void
    {
        if (!$this->children->contains($category)) {
            $this->children->add($category);
        }
    }

    /**
     * @internal
     */
    public function addArticle(Article $article): void
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }
    }

}
