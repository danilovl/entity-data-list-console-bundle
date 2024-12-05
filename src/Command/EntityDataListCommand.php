<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Command;

use Danilovl\EntityDataListConsoleBundle\Exception\LogicException;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Stringable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{
    InputOption,
    InputArgument,
    InputInterface
};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class EntityDataListCommand extends Command
{
    protected const string DEFAULT_DATE_FORMAT = 'Y-m-d H:i:s';

    protected const string NOT_AVAILABLE = 'N/A';

    abstract protected function getResult(string $className, int $limit, int $offset): array;

    abstract protected function getClassMetadata(string $className): ClassMetadata;

    abstract protected function isDoctrineEntity(string $className): bool;

    private SymfonyStyle $io;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function configure(): void
    {
        $this->configureEntityArgument();

        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of results', $this->getLimit())
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset for the results', $this->getOffset())
            ->addOption('associations-ignore', null, InputOption::VALUE_OPTIONAL, 'Associations ignore', $this->getAssociationsIgnore())
            ->addOption('associations-limit', null, InputOption::VALUE_OPTIONAL, 'Associations render limit', $this->getAssociationsLimit());
    }

    protected function configureEntityArgument(): void
    {
        if ($this->getEntityClass() !== null) {
            return;
        }

        $this->addArgument('entity', InputArgument::REQUIRED, 'The entity class (e.g., App\\Entity\\User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $className */
        $className = $this->getEntityClass() ?? $input->getArgument('entity');

        /** @var string $limit */
        $limit = $input->getOption('limit');
        $limit = (int) $limit;

        /** @var string $offset */
        $offset = $input->getOption('offset');
        $offset = (int) $offset;

        /** @var int $associationsIgnore */
        $associationsIgnore = $input->getOption('associations-ignore');
        $associationsIgnore = (bool) $associationsIgnore;

        /** @var string $associationsLimit */
        $associationsLimit = $input->getOption('associations-limit');
        $associationsLimit = (int) $associationsLimit;

        if (!class_exists($className)) {
            $this->io->error(sprintf('Entity class "%s" does not exist.', $className));

            return Command::FAILURE;
        }

        if (!$this->isDoctrineEntity($className)) {
            $this->io->error(sprintf('Entity class "%s" is not a Doctrine entity.', $className));

            return Command::FAILURE;
        }

        $results = $this->getResult($className, $limit, $offset);
        $metadata = $this->getClassMetadata($className);

        $table = new Table($output);
        $fields = $this->getFields($metadata);

        $table->setHeaders($fields);
        foreach ($results as $result) {
            $row = $this->processRow($result, $fields, $metadata, $associationsIgnore, $associationsLimit);
            $table->addRow($row);
        }

        $table->render();

        return Command::SUCCESS;
    }

    protected function getLimit(): int
    {
        return 10;
    }

    protected function getOffset(): int
    {
        return 0;
    }

    protected function getAssociationsIgnore(): int
    {
        return 1;
    }

    protected function getAssociationsLimit(): int
    {
        return 10;
    }

    protected function getEntityClass(): ?string
    {
        return null;
    }

    /**
     * @return string[]
     */
    protected function getFields(ClassMetadata $metadata): array
    {
        $fieldNames = $metadata->getFieldNames();
        $associationNames = $metadata->getAssociationNames();

        return array_merge($fieldNames, $associationNames);
    }

    protected function processRow(
        object $entity,
        array $fields,
        ClassMetadata $metadata,
        bool $ignoreAssociations,
        int $associationsLimit
    ): array {
        $row = [];

        if (!method_exists($metadata, 'getFieldValue')) {
            throw new LogicException('Metadata must have "getFieldValue" method.');
        }

        foreach ($fields as $field) {
            if ($metadata->hasField($field)) {
                $value = $metadata->getFieldValue($entity, $field);

                if ($value instanceof DateTimeInterface) {
                    $dateFormat = $this->getProcessRowDateFormat() ?? self::DEFAULT_DATE_FORMAT;
                    $row[] = $value->format($dateFormat);
                } else {
                    $row[] = $value;
                }
            } elseif (!$ignoreAssociations && $metadata->hasAssociation($field)) {
                $related = $metadata->getFieldValue($entity, $field);
                $row[] = $this->processAssociations($related, $associationsLimit);
            } else {
                $row[] = self::NOT_AVAILABLE;
            }
        }

        return $row;
    }

    /**
     * @param object|Collection|null $related
     */
    protected function processAssociations(mixed $related, int $associationsLimit): string
    {
        if ($related === null) {
            return self::NOT_AVAILABLE;
        }

        if ($related instanceof Collection) {
            $related = $related->toArray();

            /** @var array<object> $related */
            $related = array_slice($related, 0, $associationsLimit);

            return implode(', ', array_map(fn (object $item): string => $this->processAssociation($item), $related));
        }

        return $this->processAssociation($related);
    }

    protected function processAssociation(object $item): string
    {
        return $item instanceof Stringable ? (string) $item : self::NOT_AVAILABLE;
    }

    protected function getProcessRowDateFormat(): ?string
    {
        return null;
    }
}
