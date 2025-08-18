<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToMany;

#[Entity]
class Tag extends TestEntityWithCustomPrimaryKey
{

    #[Column]
    private string $label;

    /**
     * @var Collection<int, Article>
     */
    #[ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private Collection $articles;

    public function __construct(string $label)
    {
        parent::__construct();
        $this->label = $label;
        $this->articles = new ArrayCollection();
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return ReadableCollection<int, Article>
     */
    public function getArticles(): ReadableCollection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): void
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->addTag($this);
        }
    }

}
