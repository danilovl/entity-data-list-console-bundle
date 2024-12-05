<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\{
    InputOption,
    InputInterface
};
use Symfony\Component\Console\Output\OutputInterface;

abstract class OrmTranslatableEntityDataListCommand extends OrmEntityDataListCommand
{
    public function __construct(
        private readonly ContainerInterface $container,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Translatable locale', $this->getLocale());
    }

    protected function getLocale(): string
    {
        return 'en_US';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getOption('locale');

        /** @var TranslatableListener $translatable */
        $translatable = $this->container->get('gedmo.listener.translatable');
        $translatable->setDefaultLocale('en');
        $translatable->setTranslatableLocale($locale);

        return parent::execute($input, $output);
    }
}
