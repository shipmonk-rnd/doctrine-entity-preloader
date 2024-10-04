<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_unidirectional_abstract_pointer')]
class EntityWithOneToOneUnidirectionalAbstractPointer extends TestEntityWithId
{

    #[ManyToOne(targetEntity: EntityWithOneToOneUnidirectionalAbstract::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithOneToOneUnidirectionalAbstract $target;

    public function __construct(EntityWithOneToOneUnidirectionalAbstract $target)
    {
        $this->target = $target;
    }

}
