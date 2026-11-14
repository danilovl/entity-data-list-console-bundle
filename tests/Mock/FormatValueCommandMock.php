<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Mock;

use Danilovl\EntityDataListConsoleBundle\Command\OrmEntityDataListCommand;
use Doctrine\ORM\EntityManagerInterface;

class FormatValueCommandMock extends OrmEntityDataListCommand
{
    public function __construct(EntityManagerInterface $entityManager, private readonly ?string $dateFormat = null)
    {
        parent::__construct($entityManager);
    }

    public function publicFormatValue(mixed $value): string|int|float|bool|null
    {
        return $this->formatValue($value);
    }

    protected function getProcessRowDateFormat(): ?string
    {
        return $this->dateFormat;
    }
}
