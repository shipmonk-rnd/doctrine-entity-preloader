<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Issue37;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

/**
 * Test entity replicating GitHub issue #37
 * Uses associations WITHOUT explicit targetEntity attribute
 *
 * @see https://github.com/shipmonk-rnd/doctrine-entity-preloader/issues/37
 */
#[Entity]
class Employee extends TestEntityWithId
{

    #[Column]
    private string $name;

    /**
     * ManyToOne WITHOUT explicit targetEntity - relies on type inference
     */
    #[ManyToOne(inversedBy: 'subordinates')]
    #[JoinColumn(onDelete: 'SET NULL')]
    private ?Employee $supervisor;

    /**
     * OneToMany inverse side
     *
     * @var Collection<int, Employee>
     */
    #[OneToMany(targetEntity: self::class, mappedBy: 'supervisor')]
    private Collection $subordinates;

    /**
     * OneToOne WITHOUT explicit targetEntity - relies on type inference
     */
    #[OneToOne(inversedBy: 'employee')]
    #[JoinColumn(onDelete: 'SET NULL')]
    private ?EmployeeSettings $settings = null;

    public function __construct(
        ?int $number,
        string $name,
        ?self $supervisor = null,
    )
    {
        $this->number = $number;
        $this->name = $name;
        $this->supervisor = $supervisor;
        $this->subordinates = new ArrayCollection();

        $supervisor?->addSubordinate($this);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSupervisor(): ?self
    {
        return $this->supervisor;
    }

    /**
     * @return ReadableCollection<int, Employee>
     */
    public function getSubordinates(): ReadableCollection
    {
        return $this->subordinates;
    }

    public function getSettings(): ?EmployeeSettings
    {
        return $this->settings;
    }

    public function setSettings(?EmployeeSettings $settings): void
    {
        $this->settings = $settings;

        if ($settings !== null) {
            $settings->setEmployee($this);
        }
    }

    /**
     * @internal
     */
    public function addSubordinate(self $employee): void
    {
        if (!$this->subordinates->contains($employee)) {
            $this->subordinates->add($employee);
        }
    }

}
