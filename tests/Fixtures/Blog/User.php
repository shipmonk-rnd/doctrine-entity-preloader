<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use function password_hash;
use function password_verify;
use const PASSWORD_DEFAULT;

#[Entity]
class User extends Contributor
{

    #[Column]
    private string $email;

    #[Column]
    private string $passwordHash;

    public function __construct(
        string $name,
        string $email,
        string $password,
    )
    {
        parent::__construct($name);
        $this->email = $email;
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isPasswordValid(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

}
