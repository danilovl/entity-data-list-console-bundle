<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Unit\Command;

use Danilovl\EntityDataListConsoleBundle\Tests\Mock\TranslatableCommandMock;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class OrmTranslatableEntityDataListCommandTest extends TestCase
{
    public function testServiceNotFound(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $commandTester = $this->createCommandTester($container);
        $exitCode = $commandTester->execute(['entity' => 'App\\Entity\\User']);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Service "gedmo.listener.translatable" not found.',
            (string) preg_replace('~\s+~', ' ', $commandTester->getDisplay())
        );
    }

    public function testServiceWrongType(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new stdClass);

        $commandTester = $this->createCommandTester($container);
        $exitCode = $commandTester->execute(['entity' => 'App\\Entity\\User']);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            sprintf('must be an instance of "%s", "stdClass" given.', TranslatableListener::class),
            (string) preg_replace('~\s+~', ' ', $commandTester->getDisplay())
        );
    }

    public function testLocalesAreSet(): void
    {
        $listener = new TranslatableListener;

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($listener);

        $commandTester = $this->createCommandTester($container);
        $commandTester->execute([
            'entity' => 'App\\Entity\\NotExist',
            '--locale' => 'cs_CZ',
        ]);

        $this->assertSame('cs_CZ', $listener->getListenerLocale());
        $this->assertSame('en', $listener->getDefaultLocale());
    }

    private function createCommandTester(ContainerInterface $container): CommandTester
    {
        $command = new TranslatableCommandMock($container, $this->createStub(EntityManagerInterface::class));

        $application = new Application;
        $application->addCommand($command);

        return new CommandTester($application->find('test:entity-data-list:translatable'));
    }
}
