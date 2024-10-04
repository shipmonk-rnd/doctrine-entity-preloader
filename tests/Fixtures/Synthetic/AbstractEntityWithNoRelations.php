<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic;

use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;

#[Entity]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorMap(self::INHERITANCE)]
abstract class AbstractEntityWithNoRelations extends TestEntityWithId
{

    final public const INHERITANCE = [
        'a' => ChildEntityWithNoRelationsA::class,
        'b' => ChildEntityWithNoRelationsB::class,
    ];

}
