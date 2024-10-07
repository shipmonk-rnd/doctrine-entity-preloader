<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance;

use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorMap(self::INHERITANCE)]
abstract class AbstractStiEntityWithOptionalManyToOneItselfRelation extends TestEntityWithId
{

    final public const INHERITANCE = [
        'a' => ConcreteStiEntityWithOptionalManyToOneOfItselfRelation::class,
    ];

    #[ManyToOne(targetEntity: self::class)]
    #[JoinColumn(nullable: true)]
    private ?self $parent;

    public function __construct(?self $parent)
    {
        $this->parent = $parent;
    }

}
