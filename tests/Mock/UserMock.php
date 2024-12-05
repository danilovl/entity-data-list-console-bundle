<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Mock;

class UserMock
{
    public function __construct(public int $id, public string $name) {}
}
