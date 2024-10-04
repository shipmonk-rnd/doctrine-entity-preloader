<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract;

use Doctrine\ORM\Mapping\Entity;

#[Entity]
class EntityWithOneToOneUnidirectionalAbstractTargetChildB extends EntityWithOneToOneUnidirectionalAbstractTarget
{

}
