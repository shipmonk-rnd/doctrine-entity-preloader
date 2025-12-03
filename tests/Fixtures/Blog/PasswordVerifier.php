<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

interface PasswordVerifier
{

    public function isPasswordValid(string $password): bool;

}
