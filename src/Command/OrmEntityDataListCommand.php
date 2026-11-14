<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('danilovl:entity-data-list:orm', 'Render the database data of an entity.')]
class OrmEntityDataListCommand extends EntityDataListCommand
{
    public function __construct(protected EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    /**
     * @param class-string $className
     */
    protected function isDoctrineEntity(string $className): bool
    {
        $metadataFactory = $this->entityManager->getMetadataFactory();

        if ($metadataFactory->isTransient($className)) {
            return false;
        }

        $metadata = $metadataFactory->getMetadataFor($className);

        return !$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass;
    }

    /**
     * @param class-string $className
     */
    protected function getClassMetadata(string $className): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($className);
    }

    /**
     * @param class-string $className
     */
    protected function getResult(string $className, int $limit, int $offset): array
    {
        $repository = $this->entityManager->getRepository($className);
        $metadata = $this->entityManager->getClassMetadata($className);

        $queryBuilder = $repository->createQueryBuilder('e')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        foreach ($metadata->getIdentifierFieldNames() as $identifier) {
            if ($metadata->hasField($identifier)) {
                $queryBuilder->addOrderBy('e.' . $identifier, 'ASC');
            }
        }

        /** @var array $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }
}
