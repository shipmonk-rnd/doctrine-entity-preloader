<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class EntityWithManyToOneOfManyToOneAbstractEntities extends TestEntityWithId
{

    #[ManyToOne(targetEntity: EntityWithManyToOneAbstractEntityWithNoRelations::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithManyToOneAbstractEntityWithNoRelations $entityWithManyToOneAbstractEntityWithNoRelations;

    public function __construct(
        EntityWithManyToOneAbstractEntityWithNoRelations $entityWithManyToOneAbstractEntityWithNoRelations
    )
    {
        $this->entityWithManyToOneAbstractEntityWithNoRelations = $entityWithManyToOneAbstractEntityWithNoRelations;
    }

}
