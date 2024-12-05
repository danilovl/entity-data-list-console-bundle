<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->public();

    $services->load('App\\EntityDataListConsoleBundle\\', '../../../src')
        ->exclude('../../../src/DependencyInjection')
        ->exclude('../../../src/Resources');
};
