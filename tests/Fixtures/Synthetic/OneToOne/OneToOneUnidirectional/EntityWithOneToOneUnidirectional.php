<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectional;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_unidirectional')]
class EntityWithOneToOneUnidirectional extends TestEntityWithId
{

    #[OneToOne(targetEntity: EntityWithOneToOneUnidirectionalTarget::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithOneToOneUnidirectionalTarget $target;

    public function __construct(EntityWithOneToOneUnidirectionalTarget $target)
    {
        $this->target = $target;
    }

}
