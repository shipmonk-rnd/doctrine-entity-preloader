<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract;

use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_unidirectional_abstract_target')]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorMap(self::INHERITANCE)]
abstract class EntityWithOneToOneUnidirectionalAbstractTarget extends TestEntityWithId
{

    final public const INHERITANCE = [
        'a' => EntityWithOneToOneUnidirectionalAbstractTargetChildA::class,
        'b' => EntityWithOneToOneUnidirectionalAbstractTargetChildB::class,
    ];

}
