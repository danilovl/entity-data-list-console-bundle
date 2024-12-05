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

    protected function isDoctrineEntity(string $className): bool
    {
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            if ($metadata->getName() === $className) {
                return true;
            }
        }

        return false;
    }

    protected function getClassMetadata(string $className): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($className);
    }

    protected function getResult(string $className, int $limit, int $offset): array
    {
        $repository = $this->entityManager->getRepository($className);

        /** @var array $result */
        $result = $repository->createQueryBuilder('e')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
