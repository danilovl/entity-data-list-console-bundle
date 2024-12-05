<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle;

use Danilovl\EntityDataListConsoleBundle\DependencyInjection\EntityDataListConsoleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EntityDataListConsoleBundle extends Bundle
{
    public function getContainerExtension(): EntityDataListConsoleExtension
    {
        return new EntityDataListConsoleExtension;
    }
}
