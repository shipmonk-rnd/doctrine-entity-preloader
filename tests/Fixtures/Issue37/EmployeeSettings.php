<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Issue37;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\OneToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

/**
 * Test entity replicating GitHub issue #37
 * Uses associations WITHOUT explicit targetEntity attribute
 *
 * @see https://github.com/shipmonk-rnd/doctrine-entity-preloader/issues/37
 */
#[Entity]
class EmployeeSettings extends TestEntityWithId
{

    #[Column]
    private string $theme;

    #[Column]
    private string $locale;

    /**
     * OneToOne inverse side WITHOUT explicit targetEntity - relies on type inference
     */
    #[OneToOne(mappedBy: 'settings')]
    private ?Employee $employee = null;

    public function __construct(
        ?int $number,
        string $theme = 'light',
        string $locale = 'en',
    )
    {
        $this->number = $number;
        $this->theme = $theme;
        $this->locale = $locale;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): void
    {
        $this->theme = $theme;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    /**
     * @internal
     */
    public function setEmployee(?Employee $employee): void
    {
        $this->employee = $employee;
    }

}
