<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
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

        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Translatable locale', $this->getLocale());
    }

    protected function getLocale(): string
    {
        return 'en_US';
    }

    protected function getDefaultLocale(): string
    {
        return 'en';
    }

    protected function getTranslatableListenerServiceId(): string
    {
        return 'gedmo.listener.translatable';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getTranslatableListenerServiceId();

        if (!$this->container->has($serviceId)) {
            $message = sprintf(
                'Service "%s" not found. Make sure "gedmo/doctrine-extensions" is installed, the translatable listener is registered as a public service, or override "%s::getTranslatableListenerServiceId()".',
                $serviceId,
                static::class
            );

            $this->io->error($message);

            return Command::FAILURE;
        }

        $translatable = $this->container->get($serviceId);
        if (!$translatable instanceof TranslatableListener) {
            $message = sprintf(
                'Service "%s" must be an instance of "%s", "%s" given.',
                $serviceId,
                TranslatableListener::class,
                get_debug_type($translatable)
            );

            $this->io->error($message);

            return Command::FAILURE;
        }

        /** @var string $locale */
        $locale = $input->getOption('locale');

        $translatable->setDefaultLocale($this->getDefaultLocale());
        $translatable->setTranslatableLocale($locale);

        return parent::execute($input, $output);
    }
}
