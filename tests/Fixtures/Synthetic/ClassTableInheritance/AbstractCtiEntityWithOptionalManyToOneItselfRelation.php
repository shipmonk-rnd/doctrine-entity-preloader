<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ClassTableInheritance;

use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[InheritanceType('JOINED')]
#[DiscriminatorMap(self::INHERITANCE)]
abstract class AbstractCtiEntityWithOptionalManyToOneItselfRelation extends TestEntityWithId
{

    final public const INHERITANCE = [
        'a' => CtiEntityWithOptionalManyToOneOfItselfRelation::class,
    ];

    #[ManyToOne(targetEntity: self::class)]
    #[JoinColumn(nullable: true)]
    private ?self $parent;

    public function __construct(?self $parent)
    {
        $this->parent = $parent;
    }

}
