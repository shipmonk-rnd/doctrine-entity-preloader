<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ClassTableInheritance;

use Doctrine\ORM\Mapping\Entity;

#[Entity]
class CtiEntityWithOptionalManyToOneOfItselfRelation extends AbstractCtiEntityWithOptionalManyToOneItselfRelation
{

}
