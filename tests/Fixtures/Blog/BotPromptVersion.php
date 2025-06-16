<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class BotPromptVersion
{

    #[Id]
    #[Column]
    #[GeneratedValue]
    private int $id;

    #[Column]
    private int $version;

    #[Column]
    private string $prompt;

    #[OneToOne(inversedBy: 'nextVersion', cascade: ['persist', 'remove'])]
    private ?BotPromptVersion $prevVersion;

    #[OneToOne(mappedBy: 'prevVersion')]
    private ?BotPromptVersion $nextVersion;

    public function __construct(
        string $prompt,
        ?self $prevScript = null,
    )
    {
        $this->version = ($prevScript->version ?? 0) + 1;
        $this->prompt = $prompt;
        $this->prevVersion = $prevScript;
        $this->nextVersion = null;

        if ($prevScript !== null) {
            $prevScript->nextVersion = $this;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getPrevVersion(): ?self
    {
        return $this->prevVersion;
    }

    public function getNextVersion(): ?self
    {
        return $this->nextVersion;
    }

}
