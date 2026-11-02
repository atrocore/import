<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;

class V2Dot0Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-30 13:00:00');
    }

    public function up(): void
    {
        $this->migrateAction();
        $this->migrateImportFeed();
    }

    /**
     * The import feed types are subtypes of ImportFeed now. The settings of the source and of the processing, stored in
     * the "data" field before, are columns of the import feed. The subtype of the module has no fields, the subtypes of
     * the other modules are created by their migrations.
     */
    protected function migrateImportFeed(): void
    {
        $feedColumns = [
            'sheetOptions' => ['sheet_options', 'type' => 'jsonArray'],
            'fileFieldDelimiter' => ['file_field_delimiter', 'type' => 'string', 'length' => 255, 'default' => ';'],
            'format' => ['format', 'type' => 'string', 'length' => 255, 'default' => 'CSV'],
            'fileTextQualifier' => ['file_text_qualifier', 'type' => 'string', 'length' => 255, 'default' => 'doubleQuote'],
            'headerRowNumber' => ['header_row_number', 'type' => 'integer', 'default' => 1],
            'dataStartRowNumber' => ['data_start_row_number', 'type' => 'integer', 'default' => 2],
            'decimalMark' => ['decimal_mark', 'type' => 'string', 'length' => 255, 'default' => ','],
            'thousandSeparator' => ['thousand_separator', 'type' => 'string', 'length' => 255],
            'adapter' => ['adapter', 'type' => 'string', 'length' => 255],
            'entity' => ['entity', 'type' => 'string', 'length' => 255, 'default' => 'Product'],
            'rootNode' => ['root_node', 'type' => 'string', 'length' => 255],
            'excludedNodes' => ['excluded_nodes', 'type' => 'jsonArray'],
            'keptStringNodes' => ['kept_string_nodes', 'type' => 'jsonArray'],
            'delimiter' => ['delimiter', 'type' => 'string', 'length' => 255, 'default' => '~'],
            'fieldDelimiterForRelation' => ['field_delimiter_for_relation', 'type' => 'string', 'length' => 255, 'default' => '|'],
            'emptyValue' => ['empty_value', 'type' => 'string', 'length' => 255, 'default' => ''],
            'nullValue' => ['null_value', 'type' => 'string', 'length' => 255, 'default' => 'Null'],
            'markForNoRelation' => ['mark_for_no_relation', 'type' => 'string', 'length' => 255, 'default' => 'Null'],
            'markForUnlinkedAttribute' => ['mark_for_unlinked_attribute', 'type' => 'string', 'length' => 255, 'default' => 'N/A'],
            'skipValue' => ['skip_value', 'type' => 'string', 'length' => 255, 'default' => 'Skip'],
            'skipMissingSourceFields' => ['skip_missing_source_fields', 'type' => 'boolean', 'notnull' => true, 'default' => false],
            'feedPayload' => ['feed_payload', 'type' => 'text'],
        ];

        $subtypes = [
            'simple' => [],
        ];

        $fromSchema = $this->getCurrentSchema();
        if (!$fromSchema->hasTable('import_feed')) {
            return;
        }

        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('import_feed');
        foreach ($feedColumns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            unset($options['type']);
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
            }
        }

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'import_feed', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from('import_feed')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $this->updateFeedColumns($record, $feedColumns);

            if (isset($subtypes[$record['type']])) {
                $this->insertSubtypeRecord('import_feed', $record['type'], $record['id'], $subtypes[$record['type']], []);
            }
        }
    }

    /**
     * The settings of the import feed were stored in the "feedFields" of the "data" field.
     */
    protected function updateFeedColumns(array $record, array $feedColumns): void
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $feedFields = is_array($data['feedFields'] ?? null) ? $data['feedFields'] : [];

        $qb = $this->getDbal()->createQueryBuilder()
            ->update('import_feed')
            ->where('id = :id')
            ->setParameter('id', $record['id']);

        $hasValues = false;
        foreach ($feedColumns as $field => $options) {
            if (!array_key_exists($field, $feedFields) || $feedFields[$field] === null) {
                continue;
            }

            $value = $feedFields[$field];
            $qb->set($this->getDbal()->quoteIdentifier($options[0]), ":$field");
            switch ($options['type']) {
                case 'boolean':
                    $qb->setParameter($field, !empty($value), ParameterType::BOOLEAN);
                    break;
                case 'integer':
                    $qb->setParameter($field, (int)$value, ParameterType::INTEGER);
                    break;
                case 'jsonArray':
                    $qb->setParameter($field, json_encode($value));
                    break;
                default:
                    $qb->setParameter($field, is_scalar($value) ? (string)$value : json_encode($value));
            }
            $hasValues = true;
        }

        if ($hasValues) {
            $qb->executeStatement();
        }
    }

    /**
     * The action types of the module are subtypes of Action now: the settings of the type are moved from the columns
     * of the action table and from its "data" field to the table of the subtype.
     */
    protected function migrateAction(): void
    {
        $subtypes = [
            'import' => [
                'importFeedId' => ['import_feed_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'uploadAndImport' => ['upload_and_import', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'payload' => ['payload', 'type' => 'text', 'source' => 'data'],
            ],
            'reImportFailedJob' => [
                'applyToPreselectedRecords' => ['apply_to_preselected_records', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'action', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('action')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->where('type IN (:types)')
            ->setParameter('types', array_keys($subtypes), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $columns = $subtypes[$record['type']];
            $this->insertSubtypeRecord('action', $record['type'], $record['id'], $columns, $this->getActionSettings($record, $columns));
        }
    }

    /**
     * The settings of an action: a setting was stored in a column of the action table or in the "data" field.
     */
    protected function getActionSettings(array $record, array $columns): array
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $virtualFields = is_array($data['field'] ?? null) ? $data['field'] : [];

        $settings = [];
        foreach ($columns as $field => $options) {
            $settings[$field] = $options['source'] === 'data' ? ($virtualFields[$field] ?? null) : ($record[$options[0]] ?? null);
        }

        return $settings;
    }

    /**
     * The table of a subtype: its own id, the unique link to the supertype record that is removed together with
     * it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);

        $indexes = [];
        foreach ($columns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            if (!empty($options['index'])) {
                $indexes[] = $column;
            }
            unset($options['type'], $options['index'], $options['source']);
            $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['supertype_id']);

        $foreignKeyName = $this->generateForeignKeyName($tableName . '_supertype_id');
        $table->addForeignKeyConstraint($supertypeTable, ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
        $table->addIndex(['supertype_id'], $foreignKeyName);

        foreach ($indexes as $column) {
            $table->addIndex([$column], $this->generateIndexName($tableName . '_' . $column));
        }
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $columns, array $data): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        $conn = $this->getDbal();

        $exists = $conn->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('supertype_id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $conn->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setValue('supertype_id', ':supertypeId')
            ->setParameter('id', Uuid::uuid7()->toString())
            ->setParameter('supertypeId', $supertypeId);

        foreach ($columns as $field => $options) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = $data[$field];
            $parameterType = ParameterType::STRING;

            switch ($options['type']) {
                case 'boolean':
                    $value = !empty($value);
                    $parameterType = ParameterType::BOOLEAN;
                    break;
                case 'integer':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (int)$value;
                    $parameterType = ParameterType::INTEGER;
                    break;
                case 'float':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (string)(float)$value;
                    break;
                case 'jsonArray':
                    $value = json_encode($value);
                    break;
                default:
                    $value = is_scalar($value) ? (string)$value : json_encode($value);
            }

            $qb->setValue($conn->quoteIdentifier($options[0]), ":$field");
            $qb->setParameter($field, $value, $parameterType);
        }

        $qb->executeStatement();
    }

    protected function getSubtypeTableName(string $supertypeTable, string $type): string
    {
        return $supertypeTable . '_type_' . strtolower(preg_replace('/([A-Z])/', '_$1', $type));
    }

    protected function generateIndexName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'IDX_' . strtoupper($name);
    }

    protected function generateForeignKeyName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'FK_' . strtoupper($name);
    }
}
