<?php

namespace Bolt\Storage\Migration\Processor;

use Bolt\Collection\Bag;
use Bolt\Collection\MutableBag;
use Bolt\Exception\InvalidRepositoryException;
use Bolt\Storage\Database\Schema\Manager as SchemaManager;
use Bolt\Storage\Entity\Entity;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Storage\Migration\Field;
use Bolt\Storage\Migration\Result\TableResult;
use Bolt\Storage\Migration\Transformer\TypeTransformerInterface;
use Bolt\Storage\Repository;
use Doctrine\DBAL\Schema\Table;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Transformer for Entity data.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class TableRecordsProcessor
{
    /** @var EntityManager */
    private $entityManager;
    /** @var SchemaManager */
    private $schemaManager;
    /** @var MutableBag */
    private $typeTransformers;
    /** @var LoggerInterface */
    private $logger;
    /** @var int */
    private $maxResults;

    /**
     * Constructor.
     *
     * @param EntityManager        $em
     * @param SchemaManager        $manager
     * @param Bag                  $typeTransformers
     * @param LoggerInterface|null $logger
     * @param int                  $maxResults
     */
    public function __construct(EntityManager $em, SchemaManager $manager, Bag $typeTransformers, LoggerInterface $logger = null, $maxResults = 1000)
    {
        $this->entityManager = $em;
        $this->schemaManager = $manager;
        $this->typeTransformers = $typeTransformers;
        $this->maxResults = $maxResults;
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
     * Update all repositories entity field data and do any required
     * transformations.
     *
     * @param LoggerInterface $logger
     *
     * @return array
     */
    public function transform()
    {
        $results = [];
        $mapper = $this->entityManager->getMapper();
        $tables = $this->schemaManager->getInstalledTables();

        /** @var Table $table */
        foreach ($tables as $table) {
            $tableName = $table->getName();
            try {
                $repo = $this->entityManager->getRepository($tableName);
                $results[$tableName] = new TableResult($tableName, true);
            } catch (InvalidRepositoryException $e) {
                // It isn't one of our tables, don't touch it.
                continue;
            }

            $this->updateTable($tableName, $mapper, $repo, $results[$tableName]);
        }

        return $results;
    }

    /**
     * @param string         $tableName
     * @param MetadataDriver $mapper
     * @param Repository     $repo
     * @param TableResult    $result
     */
    private function updateTable($tableName, MetadataDriver $mapper, Repository $repo, TableResult $result)
    {
        $current = 0;
        $tableCount = $repo->count();
        if ($tableCount === 0) {
            return;
        }
        $className = $mapper->resolveClassName($tableName);
        /** @var array $metaData */
        $metaData = $mapper->getClassMetadata($className) ?: $mapper->getClassMetadata($tableName);
        $fieldsMeta = Bag::fromRecursive($metaData);
        $qb = $this->entityManager->getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from($tableName)
        ;

        $this->logger->info(\sprintf('Processing table: %s', $tableName));
        while (true) {
            $qb->setFirstResult($current)
                ->setMaxResults($this->maxResults)
            ;
            $rows = $this->entityManager
                ->getConnection()
                ->fetchAll($qb)
            ;
            if (empty($rows)) {
                return;
            }

            foreach ($rows as $key => $row) {
                $this->updateRow($row, $fieldsMeta, $repo, $result);
                unset($rows[$key]);
            }

            $current = $current + $this->maxResults;
        }
    }

    /**
     * @param array       $rawRecord
     * @param Bag         $metaData
     * @param Repository  $repo
     * @param TableResult $result
     */
    private function updateRow(array $rawRecord, Bag $metaData, Repository $repo, TableResult $result)
    {
        $result->addRecord();
        $this->updateColumnValues(MutableBag::from($rawRecord), $metaData['fields'], $repo, $result);
    }

    /**
     * @param MutableBag  $row
     * @param Bag         $fieldsMeta
     * @param Repository  $repo
     * @param TableResult $result
     */
    private function updateColumnValues(MutableBag $row, Bag $fieldsMeta, Repository $repo, TableResult $result)
    {
        /** @var Entity $entity */
        $entity = $repo->find($row->getPath('id'));
        $changed = false;

        foreach ($fieldsMeta as $fieldMeta) {
            $field = new Field($row, $fieldMeta);
            /** @var TypeTransformerInterface $transformer */
            foreach ($this->typeTransformers as $transformer) {
                $transformer->transform($field);
            }
            if ($field->hasChanged()) {
                $this->logger->debug(\sprintf('  - Updated field: %s', $field->getName()));
                $entity->set($field->getName(), $field->getValue());
                $result->addField();
                $changed = true;
            }
        }
        if ($changed) {
            $repo->save($entity, true);
        }
    }
}
