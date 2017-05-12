<?php

namespace Bolt\Nut;

use Bolt\Storage\Database\Schema\SchemaCheck;
use Bolt\Storage\Migration\Processor\SchemaProcessor;
use Bolt\Storage\Migration\Processor\TableRecordsProcessor;
use Bolt\Storage\Migration\Result\TableResult;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Nut command to migrate legacy data types.
 */
class DatabaseTypeMigration extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('database:type-migrate')
            ->setDescription('Make data migration changes relevant to version upgrades.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $response = $this->updateSchema();
        if ($response) {
            $this->updateDataTypes($output);
        }

        return (int) !$response;
    }

    private function updateSchema()
    {
        $this->io->title('Database Schema Update');
        if ($this->io->confirm('Would you like to continue with the update')) {
            /** @var SchemaCheck $response */
            $response = $this->app['schema']->update();
            $this->io->note('Modifications made to the database');
            $this->io->listing($response->getResponseStrings());
            $this->io->success('Your database is now up to date.');

            $this->auditLog(__CLASS__, 'Database updated');

            return true;
        }

        return false;
    }

    private function updateDataTypes(OutputInterface $output)
    {
        $this->io->title('Performing database data type migration');
        if (!$this->io->confirm('Would you like to continue with the migration')) {
            $this->io->note('Aborting migration');

            return 1;
        }

        $logger = new ConsoleLogger($output);

        /** @var SchemaProcessor $transformer */
        $transformer = $this->app['data_type.transformer.schema'];
        $transformer->setLogger($logger);
        $transformer->transform();
        /** @var TableRecordsProcessor $transformer */
        $transformer = $this->app['data_type.transformer.table_records'];
        $transformer->setLogger($logger);
        $results = $transformer ->transform();

        $table = new Table($this->io);
        $table->setHeaders(['Table', 'Records Processed', 'Fields Updated']);

        $rightAligned = new TableStyle();
        $rightAligned->setPadType(STR_PAD_LEFT);
        $table->setColumnStyle(1, $rightAligned);
        $table->setColumnStyle(2, $rightAligned);

        /** @var TableResult $result */
        foreach ($results as $result) {
            $table->addRow([$result->getTableName(), $result->getRecordCount(), $result->getFieldCount()]);
        }

        $this->io->success('Data processing complete');
        $table->render();

        $this->auditLog(__CLASS__, 'Database field data types migrated');

        return 0;
    }
}
