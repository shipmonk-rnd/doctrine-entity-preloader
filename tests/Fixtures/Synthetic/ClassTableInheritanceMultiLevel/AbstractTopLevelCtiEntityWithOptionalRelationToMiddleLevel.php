<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ClassTableInheritanceMultiLevel;

use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[InheritanceType('JOINED')]
#[DiscriminatorMap(self::INHERITANCE)]
#[Table(name: 'mutli_level_cti_entity_parent')]
abstract class AbstractTopLevelCtiEntityWithOptionalRelationToMiddleLevel extends TestEntityWithId
{

    final public const INHERITANCE = [
        'a' => ConcreteCtiEntityWithOptionalRelationToMiddleLevelParent::class,
    ];

    #[ManyToOne(targetEntity: AbstractMiddleLevelCtiEntityWithOptionalRelationToMiddleLevel::class)]
    #[JoinColumn(nullable: true)]
    private ?AbstractMiddleLevelCtiEntityWithOptionalRelationToMiddleLevel $parent;

    public function __construct(?AbstractMiddleLevelCtiEntityWithOptionalRelationToMiddleLevel $parent)
    {
        $this->parent = $parent;
    }

}
