<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Mock;

use Stringable;

class StringableMock implements Stringable
{
    public function __toString(): string
    {
        return 'stringable value';
    }
}
