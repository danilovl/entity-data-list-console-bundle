<?php declare(strict_types=1);

namespace Danilovl\EntityDataListConsoleBundle\Tests\Mock;

use Danilovl\EntityDataListConsoleBundle\Command\OrmTranslatableEntityDataListCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('test:entity-data-list:translatable')]
class TranslatableCommandMock extends OrmTranslatableEntityDataListCommand {}
