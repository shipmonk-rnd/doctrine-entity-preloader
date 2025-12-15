<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Issue37;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

/**
 * @see https://github.com/shipmonk-rnd/doctrine-entity-preloader/issues/37
 */
#[Entity]
class Employee extends TestEntityWithId
{

    /**
     * ManyToOne WITHOUT explicit targetEntity
     */
    #[ManyToOne]
    private ?Employee $supervisor;

    /**
     * OneToOne WITHOUT explicit targetEntity
     */
    #[OneToOne]
    private ?EmployeeSettings $settings;

    public function __construct(
        ?int $number,
        ?Employee $supervisor = null,
        ?EmployeeSettings $settings = null,
    )
    {
        $this->number = $number;
        $this->supervisor = $supervisor;
        $this->settings = $settings;
    }

    public function getSupervisor(): ?Employee
    {
        return $this->supervisor;
    }

    public function getSettings(): ?EmployeeSettings
    {
        return $this->settings;
    }

}
