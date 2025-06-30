<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Compat;

use Doctrine\DBAL\ParameterType;
use function enum_exists;

// phpcs:disable Generic.Classes.DuplicateClassName.Found
// phpcs:disable Generic.Files.OneTraitPerFile.MultipleFound

if (!enum_exists(ParameterType::class)) {
    trait CompatibilityType
    {

        public function getBindingType(): int
        {
            return $this->doGetBindingType();
        }

        private function doGetBindingType(): int|ParameterType
        {
            return parent::getBindingType();
        }

    }
} else {
    trait CompatibilityType
    {

        public function getBindingType(): ParameterType
        {
            return $this->doGetBindingType();
        }

        private function doGetBindingType(): int|ParameterType
        {
            return parent::getBindingType();
        }

    }
}
