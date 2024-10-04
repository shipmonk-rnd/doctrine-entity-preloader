<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_unidirectional_abstract')]
class EntityWithOneToOneUnidirectionalAbstract extends TestEntityWithId
{

    #[OneToOne(targetEntity: EntityWithOneToOneUnidirectionalAbstractTarget::class)]
    #[JoinColumn(nullable: false)]
    private EntityWithOneToOneUnidirectionalAbstractTarget $target;

    public function __construct(EntityWithOneToOneUnidirectionalAbstractTarget $target)
    {
        $this->target = $target;
    }

}
