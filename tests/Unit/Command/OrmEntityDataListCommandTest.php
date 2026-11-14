<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Unit\Command;

use Danilovl\EntityDataListConsoleBundle\Tests\Mock\UserMock;

use Doctrine\ORM\Mapping\{
    ClassMetadata,
    MappingException,
    ClassMetadataFactory
};
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\{
    Query,
    QueryBuilder,
    EntityRepository,
    EntityManagerInterface,
};
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Danilovl\EntityDataListConsoleBundle\Command\OrmEntityDataListCommand;
use Symfony\Component\Console\Tester\CommandTester;

class OrmEntityDataListCommandTest extends TestCase
{
    private Stub&EntityManagerInterface $entityManager;

    private OrmEntityDataListCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->command = new OrmEntityDataListCommand($this->entityManager);
    }

    public function testCommandSuccess(): void
    {
        $metadataMock = $this->createStub(ClassMetadata::class);
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
            ->willReturn($metadataMock);

        $user = new UserMock(1, 'Test Name');
        $mockRepository = $this->createMockRepository([$user]);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($mockRepository);

        $classMetadataFactory = $this->createMockMetadataFactory(true);

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
            ->willReturn($this->createMockMetadataFactory(false));

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

    #[DataProvider('provideInvalidOptionValueCases')]
    public function testInvalidOptionValue(string $option, string $value, string $expectedMessage): void
    {
        $commandTester = $this->createCommandTester($this->command);

        $exitCode = $commandTester->execute([
            'entity' => UserMock::class,
            $option => $value,
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertSame(
            '[ERROR] ' . $expectedMessage,
            preg_replace('~\s+~', ' ', mb_trim($commandTester->getDisplay()))
        );
    }

    public static function provideInvalidOptionValueCases(): Generator
    {
        yield 'limit not a number' => ['--limit', 'abc', 'Option "--limit" must be an integer greater than or equal to 1, "abc" given.'];
        yield 'limit zero' => ['--limit', '0', 'Option "--limit" must be an integer greater than or equal to 1, "0" given.'];
        yield 'limit float' => ['--limit', '1.5', 'Option "--limit" must be an integer greater than or equal to 1, "1.5" given.'];
        yield 'offset negative' => ['--offset', '-1', 'Option "--offset" must be an integer greater than or equal to 0, "-1" given.'];
        yield 'associations limit not a number' => ['--associations-limit', 'x', 'Option "--associations-limit" must be an integer greater than or equal to 0, "x" given.'];
    }

    public function testLimitOptionRequiresValue(): void
    {
        $application = new Application;
        $application->addCommand($this->command);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "--limit" option requires a value.');

        $input = new ArgvInput(['console', 'danilovl:entity-data-list:orm', UserMock::class, '--limit']);
        $application->run($input, new NullOutput);
    }

    public function testEntityClassWithLeadingBackslash(): void
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory
            ->expects($this->once())
            ->method('isTransient')
            ->with(UserMock::class)
            ->willReturn(true);

        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($factory);

        $commandTester = $this->createCommandTester($this->command);
        $exitCode = $commandTester->execute(['entity' => '\\' . UserMock::class]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertSame(
            '[ERROR] Entity class "Danilovl\EntityDataListConsoleBundle\Tests\Mock\UserMock" is not a Doctrine entity.',
            preg_replace('~\s+~', ' ', mb_trim($commandTester->getDisplay()))
        );
    }

    #[DataProvider('provideNotQueryableMetadataIsNotDoctrineEntityCases')]
    public function testNotQueryableMetadataIsNotDoctrineEntity(string $property): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->{$property} = true;

        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn(false);
        $factory->method('getMetadataFor')->willReturn($metadata);

        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($factory);

        $commandTester = $this->createCommandTester($this->command);
        $exitCode = $commandTester->execute(['entity' => UserMock::class]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('is not a Doctrine entity.', $commandTester->getDisplay());
    }

    public static function provideNotQueryableMetadataIsNotDoctrineEntityCases(): Generator
    {
        yield 'mapped superclass' => ['isMappedSuperclass'];
        yield 'embeddable' => ['isEmbeddedClass'];
    }

    public function testResultOrderedByIdentifier(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id', 'author']);
        $metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'id');
        $metadata->method('getFieldNames')->willReturn(['id']);
        $metadata->method('getAssociationNames')->willReturn([]);

        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('e.id', 'ASC')
            ->willReturnSelf();

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($this->createMockMetadataFactory(true));

        $commandTester = $this->createCommandTester($this->command);
        $exitCode = $commandTester->execute(['entity' => UserMock::class]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
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
        $repository = $this->createStub(EntityRepository::class);

        $repository->method('createQueryBuilder')->willReturnCallback(function () use ($data) {
            $query = $this->createStub(Query::class);
            $query->method('getResult')->willReturn($data);

            $queryBuilder = $this->createStub(QueryBuilder::class);
            $queryBuilder->method('setMaxResults')->willReturnSelf();
            $queryBuilder->method('setFirstResult')->willReturnSelf();
            $queryBuilder->method('addOrderBy')->willReturnSelf();
            $queryBuilder->method('getQuery')->willReturn($query);

            return $queryBuilder;
        });

        return $repository;
    }

    private function createMockMetadataFactory(bool $isEntity): object
    {
        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn(!$isEntity);
        $factory->method('getMetadataFor')->willReturn($this->createStub(ClassMetadata::class));

        return $factory;
    }
}
