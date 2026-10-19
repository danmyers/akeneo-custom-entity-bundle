<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Command;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntityTranslation;
use DanMyers\CustomEntityBundle\Installer\JobInstanceInstaller;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prepares an existing PIM for the bundle: creates the quick export job
 * instances and shows (or applies) the SQL needed for the configured custom
 * entity tables.
 */
class InstallCommand extends Command
{
    protected static $defaultName = 'danmyers:custom-entity:install';
    protected static $defaultDescription = 'Creates the custom entity job instances and database tables.';

    public function __construct(
        private readonly JobInstanceInstaller $jobInstanceInstaller,
        private readonly CustomEntityRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Execute the SQL that creates or updates the custom entity tables.')
            ->addOption('skip-schema', null, InputOption::VALUE_NONE, 'Only install the job instances.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $this->jobInstanceInstaller->install();
        $io->writeln([] === $created
            ? 'Quick export job instances already exist.'
            : sprintf('Created job instances: %s.', implode(', ', $created)));

        if ($input->getOption('skip-schema')) {
            return Command::SUCCESS;
        }

        $sql = $this->getSchemaUpdateSql();
        if ([] === $sql) {
            $io->success('The custom entity tables are up to date.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $io->section('SQL needed for the custom entity tables');
            $io->writeln(array_map(static fn (string $query): string => $query . ';', $sql));
            $io->note('Run the command again with --force to execute it, or add it to a migration.');

            return Command::SUCCESS;
        }

        $connection = $this->entityManager->getConnection();
        foreach ($sql as $query) {
            $connection->executeStatement($query);
        }
        $io->success(sprintf('%d SQL statement(s) executed.', count($sql)));

        return Command::SUCCESS;
    }

    /**
     * Only the statements touching the tables of custom entities (and their
     * translation and join tables) are returned, never changes to Akeneo's
     * own tables.
     *
     * @return string[]
     */
    private function getSchemaUpdateSql(): array
    {
        $metadataFactory = $this->entityManager->getMetadataFactory();
        $tables = [];
        $classes = [];

        foreach ($this->registry->all() as $definition) {
            $metadata = $metadataFactory->getMetadataFor($definition->getClass());
            $classes[$metadata->getName()] = $metadata;
            $tables[] = $metadata->getTableName();

            foreach ($metadata->getAssociationMappings() as $mapping) {
                if (isset($mapping['joinTable']['name'])) {
                    $tables[] = $mapping['joinTable']['name'];
                }
                $target = $metadataFactory->getMetadataFor($mapping['targetEntity']);
                if (null !== $this->registry->findByClass($target->getName()) || is_a($target->getName(), AbstractCustomEntityTranslation::class, true)) {
                    $classes[$target->getName()] = $target;
                    $tables[] = $target->getTableName();
                }
            }
        }

        $sql = (new SchemaTool($this->entityManager))->getUpdateSchemaSql(array_values($classes), true);
        $tables = array_unique($tables);

        return array_values(array_filter($sql, static function (string $query) use ($tables): bool {
            foreach ($tables as $table) {
                if (preg_match(sprintf('/\b%s\b/', preg_quote($table, '/')), $query)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
