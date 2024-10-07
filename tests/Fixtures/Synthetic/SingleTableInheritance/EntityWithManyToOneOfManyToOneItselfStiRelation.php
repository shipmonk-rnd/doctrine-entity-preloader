<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
class EntityWithManyToOneOfManyToOneItselfStiRelation extends TestEntityWithId
{

    #[ManyToOne(targetEntity: AbstractStiEntityWithOptionalManyToOneItselfRelation::class)]
    #[JoinColumn(nullable: false)]
    private AbstractStiEntityWithOptionalManyToOneItselfRelation $abstractEntityWithOptionalManyToOneItselfRelation;

    public function __construct(
        AbstractStiEntityWithOptionalManyToOneItselfRelation $abstractEntityWithOptionalManyToOneItselfRelation
    )
    {
        $this->abstractEntityWithOptionalManyToOneItselfRelation = $abstractEntityWithOptionalManyToOneItselfRelation;
    }

}
