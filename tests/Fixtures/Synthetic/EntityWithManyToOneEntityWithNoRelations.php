<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class EntityWithManyToOneEntityWithNoRelations extends TestEntityWithId
{

    #[ManyToOne(targetEntity: EntityWithNoRelations::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithNoRelations $entityWithNoRelations;

    public function __construct(EntityWithNoRelations $entityWithNoRelations)
    {
        $this->entityWithNoRelations = $entityWithNoRelations;
    }

}
