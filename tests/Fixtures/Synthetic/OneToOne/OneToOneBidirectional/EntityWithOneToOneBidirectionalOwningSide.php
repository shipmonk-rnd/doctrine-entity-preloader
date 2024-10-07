<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneBidirectional;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_bidirectional')]
class EntityWithOneToOneBidirectionalOwningSide extends TestEntityWithId
{

    #[OneToOne(targetEntity: EntityWithOneToOneBidirectionalInverseSide::class, inversedBy: 'back')]
    #[JoinColumn(nullable: false)]
    private EntityWithOneToOneBidirectionalInverseSide $target;

    public function __construct(EntityWithOneToOneBidirectionalInverseSide $target)
    {
        $this->target = $target;
    }

}
