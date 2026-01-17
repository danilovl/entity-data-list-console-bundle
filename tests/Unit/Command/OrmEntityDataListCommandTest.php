<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Unit\Command;

use Danilovl\EntityDataListConsoleBundle\Tests\Mock\UserMock;

use Doctrine\ORM\Mapping\{
    ClassMetadata,
    MappingException,
    ClassMetadataFactory
};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\{
    Query,
    QueryBuilder,
    EntityRepository,
    EntityManagerInterface,
};
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Danilovl\EntityDataListConsoleBundle\Command\OrmEntityDataListCommand;
use Symfony\Component\Console\Tester\CommandTester;

class OrmEntityDataListCommandTest extends TestCase
{
    private MockObject&EntityManagerInterface $entityManager;

    private OrmEntityDataListCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->command = new OrmEntityDataListCommand($this->entityManager);
    }

    public function testCommandSuccess(): void
    {
        $metadataMock = $this->createMock(ClassMetadata::class);
        $metadataMock->method('getFieldNames')->willReturn(['id', 'name']);

        $metadataMock
            ->method('hasField')
            ->willReturnCallback(static fn (string $field): bool => in_array($field, ['id', 'name']));

        $metadataMock
            ->method('getFieldValue')
            ->willReturnCallback(static function (object $entity, string $field): mixed {
                if ($field === 'id') {
                    return 1;
                }

                if ($field === 'name') {
                    return 'Test Name';
                }

                return null;
            });

        $this->entityManager
            ->method('getClassMetadata')
            ->with(UserMock::class)
            ->willReturn($metadataMock);

        $user = new UserMock(1, 'Test Name');
        $mockRepository = $this->createMockRepository([$user]);

        $this->entityManager
            ->method('getRepository')
            ->with(UserMock::class)
            ->willReturn($mockRepository);

        $metadataMock = $this->createMock(ClassMetadata::class);
        $metadataMock->method('getName')->willReturn(UserMock::class);

        $classMetadataFactory = $this->createMockMetadataFactory([$metadataMock]);

        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($classMetadataFactory);

        $commandTester = $this->createCommandTester($this->command);
        $exitCode = $commandTester->execute([
            'entity' => 'Danilovl\\EntityDataListConsoleBundle\\Tests\\Mock\\UserMock',
            '--limit' => 10,
            '--offset' => 0,
        ]);

        $output = $commandTester->getDisplay();

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('name', $output);
        $this->assertStringContainsString('1', $output);
        $this->assertStringContainsString('Test Name', $output);
    }

    public function testInvalidEntityClass(): void
    {
        $this->entityManager
            ->method('getClassMetadata')
            ->willThrowException(new MappingException);

        $commandTester = $this->createCommandTester($this->command);

        $exitCode = $commandTester->execute([
            'entity' => 'App\\Entity\\InvalidClass',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertSame(
            '[ERROR] Entity class "App\Entity\InvalidClass" does not exist.',
            preg_replace('~\s+~', ' ', mb_trim($output))
        );
    }

    public function testEntityNotDoctrineEntity(): void
    {
        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($this->createMockMetadataFactory([]));

        $commandTester = $this->createCommandTester($this->command);

        $exitCode = $commandTester->execute([
            'entity' => 'Danilovl\\EntityDataListConsoleBundle\\Tests\\Mock\\UserMock',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertSame(
            '[ERROR] Entity class "Danilovl\EntityDataListConsoleBundle\Tests\Mock\UserMock" is not a Doctrine entity.',
            preg_replace('~\s+~', ' ', mb_trim($output))
        );
    }

    private function createCommandTester(OrmEntityDataListCommand $ormEntityDataListCommand): CommandTester
    {
        $application = new Application;
        $application->addCommand($ormEntityDataListCommand);

        /** @var string $commandName */
        $commandName = $ormEntityDataListCommand->getName();
        $command = $application->find($commandName);

        return new CommandTester($command);
    }

    private function createMockRepository(array $data): object
    {
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('createQueryBuilder')->willReturnCallback(function () use ($data) {
            $query = $this->createMock(Query::class);
            $query->method('getResult')->willReturn($data);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('setMaxResults')->willReturnSelf();
            $queryBuilder->method('setFirstResult')->willReturnSelf();
            $queryBuilder->method('getQuery')->willReturn($query);

            return $queryBuilder;
        });

        return $repository;
    }

    private function createMockMetadataFactory(array $metadata): object
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($metadata);

        return $factory;
    }
}
