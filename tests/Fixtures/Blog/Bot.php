<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\OneToOne;
use function hash;
use function hash_equals;

#[Entity]
class Bot extends Contributor
{

    #[OneToOne(cascade: ['persist', 'remove'])]
    private BotPromptVersion $activePrompt;

    #[Column]
    private string $apiKeyHash;

    public function __construct(string $name, string $apiKey, string $prompt)
    {
        parent::__construct($name);
        $this->activePrompt = new BotPromptVersion($prompt);
        $this->apiKeyHash = hash('sha256', $apiKey);
    }

    public function getActivePrompt(): BotPromptVersion
    {
        return $this->activePrompt;
    }

    public function changePrompt(string $newPrompt): void
    {
        $this->activePrompt = new BotPromptVersion($newPrompt, $this->activePrompt);
    }

    public function isApiKeyValid(string $apiKey): bool
    {
        return hash_equals($this->apiKeyHash, hash('sha256', $apiKey));
    }

}
