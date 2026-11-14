<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Unit\Command;

use Danilovl\EntityDataListConsoleBundle\Tests\Mock\{
    StringableMock,
    FormatValueCommandMock,
    StatusUnitEnumMock,
    StatusBackedEnumMock
};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class EntityDataListCommandFormatValueTest extends TestCase
{
    #[DataProvider('provideFormatValueCases')]
    public function testFormatValue(mixed $value, string|int|float|bool|null $expected): void
    {
        $command = $this->createCommand();

        $this->assertSame($expected, $command->publicFormatValue($value));
    }

    public function testFormatValueResource(): void
    {
        $command = $this->createCommand();

        $resource = fopen('php://memory', 'rb');
        $this->assertIsResource($resource);

        try {
            $this->assertSame('N/A', $command->publicFormatValue($resource));
        } finally {
            fclose($resource);
        }
    }

    public function testFormatValueCustomDateFormat(): void
    {
        $command = $this->createCommand('d.m.Y');

        $this->assertSame('14.11.2026', $command->publicFormatValue(new DateTimeImmutable('2026-11-14 10:20:30')));
    }

    public static function provideFormatValueCases(): Generator
    {
        yield 'null' => [null, null];
        yield 'string' => ['text', 'text'];
        yield 'int' => [42, 42];
        yield 'float' => [1.5, 1.5];
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'datetime default format' => [new DateTimeImmutable('2026-11-14 10:20:30'), '2026-11-14 10:20:30'];
        yield 'backed enum' => [StatusBackedEnumMock::Active, 'active'];
        yield 'unit enum' => [StatusUnitEnumMock::Draft, 'Draft'];
        yield 'array' => [['a' => 1, 'b' => 'тест'], '{"a":1,"b":"тест"}'];
        yield 'list' => [[1, 2, 3], '[1,2,3]'];
        yield 'empty array' => [[], '[]'];
        yield 'array with slashes' => [['url' => 'https://example.com/a'], '{"url":"https://example.com/a"}'];
        yield 'array with invalid utf8' => [['a' => "\xB1"], '{"a":"' . "\u{FFFD}" . '"}'];
        yield 'stringable' => [new StringableMock, 'stringable value'];
        yield 'plain object' => [new stdClass, stdClass::class];
    }

    private function createCommand(?string $dateFormat = null): FormatValueCommandMock
    {
        return new FormatValueCommandMock($this->createStub(EntityManagerInterface::class), $dateFormat);
    }
}
