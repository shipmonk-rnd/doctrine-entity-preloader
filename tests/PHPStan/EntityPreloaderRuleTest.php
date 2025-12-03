<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\PHPStan;

use PHPStan\Rules\Rule;
use ShipMonk\DoctrineEntityPreloader\PHPStan\EntityPreloaderRule;
use ShipMonk\PHPStanDev\RuleTestCase;

/**
 * @extends RuleTestCase<EntityPreloaderRule>
 */
final class EntityPreloaderRuleTest extends RuleTestCase
{

    protected function getRule(): Rule
    {
        return new EntityPreloaderRule();
    }

    public function testRule(): void
    {
        $this->analyzeFiles([__DIR__ . '/Data/EntityPreloaderRuleTestData.php']);
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

}
