<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Extension\Extension
};
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class EntityDataListConsoleExtension extends Extension
{
    private const string DIR_CONFIG = '/../Resources/config';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . self::DIR_CONFIG));
        $loader->load('services.yaml');
    }
}
