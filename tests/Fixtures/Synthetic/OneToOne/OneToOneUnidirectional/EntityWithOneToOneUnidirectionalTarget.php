<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectional;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_unidirectional_target')]
class EntityWithOneToOneUnidirectionalTarget extends TestEntityWithId
{

}
