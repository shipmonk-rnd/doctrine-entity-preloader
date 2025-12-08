<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use ShipMonk\DoctrineEntityPreloader\PHPStan\EntityPreloaderRule;
use ShipMonk\PHPStanDev\RuleTestCase;

/**
 * @extends RuleTestCase<EntityPreloaderRule>
 */
final class EntityPreloaderRuleWithMetadataTest extends RuleTestCase
{

    protected function getRule(): Rule
    {
        return new EntityPreloaderRule(new ObjectMetadataResolver( // @phpstan-ignore phpstanApi.constructor
            __DIR__ . '/object-manager-loader.php',
            __DIR__ . '/../../cache',
        ));
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
