<?php

namespace Bolt\Storage\Migration\Processor;

use Bolt\Collection\Bag;
use Bolt\Storage\Database\Schema\Manager;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Migration\Result\SchemaResult;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Platform schema transformer.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class SchemaProcessor
{
    /** @var EntityManager */
    private $em;
    /** @var Manager */
    private $manager;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $platformName;

    /**
     * Constructor.
     *
     * @param EntityManager        $em
     * @param Manager              $manager
     * @param LoggerInterface|null $logger
     */
    public function __construct(EntityManager $em, Manager $manager, LoggerInterface $logger = null)
    {
        $this->em = $em;
        $this->manager = $manager;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws DBALException
     *
     * @return SchemaResult
     */
    public function transform()
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();
        $this->platformName = $platform->getName();
        $result = new SchemaResult($this->platformName);

        $tableChanges = $this->getChanges();

        if ($this->platformName === 'sqlite') {
            $this->updateSqlite($tableChanges, $result);
        } elseif ($this->platformName === 'mysql') {
            $this->updateMySql($tableChanges, $result);
        } elseif ($this->platformName === 'postgresql') {
            $this->updatePostgreSql($tableChanges, $result);
        } else {
            throw new DBALException(sprintf('Unsupported platform: %s', $this->platformName));
        }

        return $result;
    }

    /**
     * @param Bag          $tableChanges
     * @param SchemaResult $result
     */
    private function updateSqlite(Bag $tableChanges, SchemaResult $result)
    {
        foreach ($tableChanges as $tableName => $fieldChanges) {
            $result->addResult(sprintf('Attempting schema data type updates on %s table', $tableName));
        }
    }

    /**
     * @param Bag          $tableChanges
     * @param SchemaResult $result
     */
    private function updateMySql(Bag $tableChanges, SchemaResult $result)
    {
        foreach ($tableChanges as $tableName => $fieldChanges) {
            $result->addResult(sprintf('Attempting schema data type updates on %s table', $tableName));
        }
    }

    /**
     * @param Bag          $tableChanges
     * @param SchemaResult $result
     */
    private function updatePostgreSql(Bag $tableChanges, SchemaResult $result)
    {
        foreach ($tableChanges as $tableName => $fieldChanges) {
            $result->addResult(sprintf('Attempting schema data type updates on %s table', $tableName));
            foreach ($fieldChanges as $fieldName => $fieldQueries) {
                /** @var QueryBuilder $query */
                foreach ($fieldQueries['queries'] as $query) {
                    $this->em->getConnection()->executeUpdate($query);
                }
                foreach ($fieldQueries['sql'] as $sql) {
                    $this->em->getConnection()->query($sql);
                }
            }
        }
    }

    /**
     * Get all the changes to all the configured tables.
     *
     * @return Bag
     */
    private function getChanges()
    {
        $installedTables = $this->manager->getInstalledTables();
        $configuredTables = $this->manager->getSchemaTables();
        $tableChanges = [];

        /** @var Table $configuredTable */
        foreach ($configuredTables as $tableAlias => $configuredTable) {
            if (!isset($installedTables[$tableAlias])) {
                continue;
            }
            $changeSet = $this->getTableUpdates($tableAlias, $configuredTable, $installedTables);
            if ($changeSet !== null) {
                $tableName = $configuredTable->getName();
                $tableChanges[$tableName] = $changeSet;
            }
        }

        return Bag::from($tableChanges);
    }

    /**
     * Get the changes for a specific table.
     *
     * @param string $tableAlias
     * @param Table  $configuredTable
     * @param array  $installedTables
     *
     * @return array|null
     */
    private function getTableUpdates($tableAlias, Table $configuredTable, array $installedTables)
    {
        /** @var Table $installedTable */
        $installedTable = $installedTables[$tableAlias];
        $configuredColumns = $configuredTable->getColumns();
        $changes = null;
        /** @var Column $configuredColumn */
        foreach ($configuredColumns as $key => $configuredColumn) {
            $columnName = $configuredColumn->getName();
            /** @var Column $installedColumn */
            $installedColumn = $installedTable->getColumn($columnName);
            if ($installedColumn === null) {
                continue;
            }
            $configuredType = $configuredColumn->getType()->getName();
            $installedType = $installedColumn->getType()->getName();
            if ($configuredType === $installedType) {
                continue;
            }
            if ($configuredType === Type::JSON_ARRAY && $this->platformName === 'postgresql' && $this->hasPostgresJsonType()) {
                $changes[$columnName] = $this->getPostgresJsonColumnUpdates($configuredTable, $configuredColumn, $installedColumn);
            }
        }

        return $changes;
    }

    /**
     * Get the queries to update any existing JSON fields to the native data type,
     * if supported by the platform.
     *
     * @param Table  $configuredTable
     * @param Column $configuredColumn
     * @param Column $installedColumn
     *
     * @return Bag
     */
    private function getPostgresJsonColumnUpdates(Table $configuredTable, Column $configuredColumn, Column $installedColumn)
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();
        $tableName = $configuredTable->getName();
        $columnName = $configuredColumn->getName();

        $queries[] = $this->em->createQueryBuilder()
            ->update($configuredTable->getName())
            ->set($columnName, 'NULL')
            ->where("$columnName = ''")
        ;
        $columnDiff[] = new ColumnDiff($columnName, $configuredColumn, ['type'], $installedColumn);
        $tableDiff = new TableDiff($tableName, [], $columnDiff);
        $sql = $platform->getAlterTableSQL($tableDiff);
        foreach ($sql as $k => $v) {
            if (preg_match('/ TYPE JSON$/', $v)) {
                $sql[$k] = "$v USING $columnName::JSON";
            }
        }
        // DBAL will try and change the TYPE before, the field type can be changed (with a cast),
        // so "DROP DEFAULT" needs to be first
        krsort($sql);

        return Bag::from(['queries' => $queries, 'sql' => $sql]);
    }

    /**
     * Check if the installed Postgres version supports a JSON data type.
     *
     * @return bool
     */
    private function hasPostgresJsonType()
    {
        $query = $this->em->createQueryBuilder()
            ->select('*')
            ->from('pg_type')
            ->where('pg_type.typname = :json')
            ->orWhere('pg_type.typname = :jsonb')
            ->setParameter('json', 'json')
            ->setParameter('jsonb', 'jsonb')
        ;
        $result = $query->execute()->fetchAll();
        if ((empty($result))) {
            return false;
        }

        return true;
    }
}
