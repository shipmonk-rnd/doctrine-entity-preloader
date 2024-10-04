<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance\EntityWithManyToOneOfManyToOneItselfStiRelation;

#[Entity]
#[Table(name: 'entity_with_n_to_1_of_n_to_1_itself_relation')]
class EntityWithManyToOneOfManyToOneOfManyToOneItselfRelation extends TestEntityWithId
{

    #[ManyToOne(targetEntity: EntityWithManyToOneOfManyToOneItselfStiRelation::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithManyToOneOfManyToOneItselfStiRelation $entityWithManyToOneOfManyToOneItselfRelation;

    public function __construct(
        EntityWithManyToOneOfManyToOneItselfStiRelation $entityWithManyToOneOfManyToOneItselfRelation
    )
    {
        $this->entityWithManyToOneOfManyToOneItselfRelation = $entityWithManyToOneOfManyToOneItselfRelation;
    }

}
