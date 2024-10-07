<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance;

use Doctrine\ORM\Mapping\Entity;

#[Entity]
class ConcreteStiEntityWithOptionalManyToOneOfItselfRelation extends AbstractStiEntityWithOptionalManyToOneItselfRelation
{

}
