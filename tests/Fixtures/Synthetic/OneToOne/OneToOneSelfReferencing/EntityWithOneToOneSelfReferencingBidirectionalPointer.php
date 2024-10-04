<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneSelfReferencing;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_self_referencing_bidirectional_pointer')]
class EntityWithOneToOneSelfReferencingBidirectionalPointer extends TestEntityWithId
{

    #[OneToOne(targetEntity: EntityWithOneToOneSelfReferencingBidirectional::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithOneToOneSelfReferencingBidirectional $target;

    public function __construct(EntityWithOneToOneSelfReferencingBidirectional $target)
    {
        $this->target = $target;
    }

}
