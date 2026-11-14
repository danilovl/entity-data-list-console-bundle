<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Command;

use use Danilovl\EntityDataListConsoleBundle\Exception\LogicException;
use BackedEnum;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Stringable;
use UnitEnum;
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

    /**
     * @param class-string $className
     */
    abstract protected function getResult(string $className, int $limit, int $offset): array;

    /**
     * @param class-string $className
     */
    abstract protected function getClassMetadata(string $className): ClassMetadata;

    /**
     * @param class-string $className
     */
    abstract protected function isDoctrineEntity(string $className): bool;

    protected SymfonyStyle $io;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function configure(): void
    {
        $this->configureEntityArgument();

        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit the number of results', $this->getLimit())
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Offset for the results', $this->getOffset())
            ->addOption('associations-ignore', null, InputOption::VALUE_OPTIONAL, 'Associations ignore', $this->getAssociationsIgnore())
            ->addOption('associations-limit', null, InputOption::VALUE_REQUIRED, 'Associations render limit', $this->getAssociationsLimit());
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
        $className = mb_ltrim($className, '\\');

        $limit = $this->getIntOption($input, 'limit', 1);
        $offset = $this->getIntOption($input, 'offset', 0);
        $associationsLimit = $this->getIntOption($input, 'associations-limit', 0);

        if ($limit === null || $offset === null || $associationsLimit === null) {
            return Command::FAILURE;
        }

        /** @var int $associationsIgnore */
        $associationsIgnore = $input->getOption('associations-ignore');
        $associationsIgnore = (bool) $associationsIgnore;

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

    private function getIntOption(InputInterface $input, string $name, int $min): ?int
    {
        /** @var int|string $value */
        $value = $input->getOption($name);
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]);

        if ($result === false) {
            $message = sprintf(
                'Option "--%s" must be an integer greater than or equal to %d, "%s" given.',
                $name,
                $min,
                $value
            );

            $this->io->error($message);

            return null;
        }

        return $result;
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
                $row[] = $this->formatValue($value);
            } elseif (!$ignoreAssociations && $metadata->hasAssociation($field)) {
                $related = $metadata->getFieldValue($entity, $field);
                $row[] = $this->processAssociations($related, $associationsLimit);
            } else {
                $row[] = self::NOT_AVAILABLE;
            }
        }

        return $row;
    }

    protected function processAssociations(mixed $related, int $associationsLimit): string
    {
        if (!is_object($related)) {
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

    protected function formatValue(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof DateTimeInterface) {
            $dateFormat = $this->getProcessRowDateFormat() ?? self::DEFAULT_DATE_FORMAT;

            return $value->format($dateFormat);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            return $json === false ? self::NOT_AVAILABLE : $json;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_resource($value)) {
            return self::NOT_AVAILABLE;
        }

        /** @var string|int|float|bool|null $value */
        return $value;
    }

    protected function getProcessRowDateFormat(): ?string
    {
        return null;
    }
}
