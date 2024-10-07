<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ClassTableInheritanceMultiLevel;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'mutli_level_cti_entity_middle_level_parent')]
abstract class AbstractMiddleLevelCtiEntityWithOptionalRelationToMiddleLevel extends AbstractTopLevelCtiEntityWithOptionalRelationToMiddleLevel
{

}
