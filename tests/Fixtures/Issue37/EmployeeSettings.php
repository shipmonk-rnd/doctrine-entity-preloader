<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Issue37;

use Doctrine\ORM\Mapping\Entity;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

/**
 * @see https://github.com/shipmonk-rnd/doctrine-entity-preloader/issues/37
 */
#[Entity]
class EmployeeSettings extends TestEntityWithId
{

    public function __construct(?int $number)
    {
        $this->number = $number;
    }

}
