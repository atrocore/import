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

namespace Import\Console;

use Atro\Console\AbstractConsole;
use Atro\Core\Utils\Util;

class CreateImportProcessingType extends AbstractConsole
{
    public const DIR = 'data/custom-code/ImportProcessingTypes';

    public static function getDescription(): string
    {
        return 'The system creates custom import processing type class. You can find the class in data/custom-code/ImportProcessingTypes/ folder and modify the code.';
    }

    public function run(array $data): void
    {
        $className = $data['className'] ?? null;

        $fileName = "data/custom-code/ImportProcessingTypes/{$className}.php";

        if (file_exists($fileName)){
            self::show('Such ImportProcessingType class already exists.', self::ERROR, true);
        }

        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $className)) {
            self::show('Class name must start with an uppercase letter and contain only letters, numbers, and underscores.', self::ERROR, true);
        }

        $content = <<<'EOD'
<?php

namespace ImportProcessingTypes;

use Atro\Entities\Job;
use Import\ProcessingTypes\AbstractProcessingType;

class {{name}} extends AbstractProcessingType
{
    public static function getTypeLabel(): ?string
    {
        return '{{name}}';
    }

    public static function getDescription(): ?string
    {
        return 'Describe {{name}}';
    }
    
    public static function getEntityName(): string
    {
        return 'Product';
    }
    
    public function runNow(array $data, ?Job $job = null): void
    {    
        /**
         * Usage Example
         */
//        $entityName = self::getEntityName();
//        $service = $this->getService(self::getEntityName());
//
//        $importJobId = $data['data']['importJobId'] ?? null;
//
//        $rowNumber = 0;
//        while (!empty($inputData = $this->getInputData($data))) {
//            foreach ($inputData['list'] ?? [] as $item) {
//                if (empty($item['name'])) {
//                    continue;
//                }
//
//                $log = $this->getEntityManager()->getEntity('ImportJobLog');
//                $log->set([
//                    'type'        => 'skip',
//                    'entityName'  => $entityName,
//                    'importJobId' => $importJobId,
//                    'rowNumber'   => $rowNumber
//                ]);
//
//                $product = $this->getEntityManager()->getRepository('Product')
//                    ->where([
//                        'name' => $item['name']
//                    ])
//                    ->findOne();
//
//                $input = new \stdClass();
//                $input->name = $item['name'];
//
//                try {
//                    if (empty($product)) {
//                        $product = $service->createEntity($input);
//                        $log->set('type', 'create');
//                        $log->set('entityId', $product->get('id'));
//                    } else {
//                        $service->updateEntity($product->get('id'), $input);
//                        $log->set('type', 'update');
//                        $log->set('entityId', $product->get('id'));
//                    }
//                } catch (\Throwable $e) {
//                    if (!$e instanceof NotModified) {
//                        $message = empty($e->getMessage()) ? $this->getCodeMessage($e->getCode()) : $e->getMessage();
//                        $log->set('type', 'error');
//                        $log->set('message', $message);
//                    }
//                }
//
//                $log->set('row', $input);
//
//                $this->getEntityManager()->saveEntity($log);
//
//                $rowNumber++;
//            }
//        }
    }
}

EOD;

        Util::createDir(self::DIR);
        file_put_contents($fileName, str_replace('{{name}}', $className, $content));

        self::show("Import Processing Type class '" . self::DIR . "/{$className}.php' has been created successfully.", self::SUCCESS);

        // clear cache
        exec('php console.php clear cache');
    }
}
