<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ClassTableInheritance;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
class EntityWithManyToOneOfManyToOneItselfCtiRelation extends TestEntityWithId
{

    #[ManyToOne(targetEntity: AbstractCtiEntityWithOptionalManyToOneItselfRelation::class)]
    #[JoinColumn(nullable: false)]
    private AbstractCtiEntityWithOptionalManyToOneItselfRelation $abstractEntityWithOptionalManyToOneItselfRelation;

    public function __construct(
        AbstractCtiEntityWithOptionalManyToOneItselfRelation $abstractEntityWithOptionalManyToOneItselfRelation
    )
    {
        $this->abstractEntityWithOptionalManyToOneItselfRelation = $abstractEntityWithOptionalManyToOneItselfRelation;
    }

}
