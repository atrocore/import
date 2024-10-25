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

namespace Import\Core\Utils;

class JsonToVerticalArray
{
    private static ?string $nullValue = null;
    private static ?string $emptyValue = '';
    private static ?string $rootNode = null;
    private static array $excludedNodes = [];
    private static array $keptStringNodes = [];

    public static function mutate(string $json, ?array $importPayload = null): array
    {
        $array = @json_decode($json, true);
        if (empty($array)) {
            return [];
        }

        if (!empty($importPayload)) {
            self::configure($importPayload);
        }

        if (!empty(self::$rootNode)) {
            $parts = explode('.', self::$rootNode);
            foreach ($parts as $part) {
                $array = $array[$part] ?? [];
            }
        }

        if (empty($array)) {
            return [];
        }

        if (self::isAssociative($array)) {
            $chunks = [$array];
        } else {
            $chunks = array_chunk($array, 100);
        }
        unset($array);

        $data = [];
        $columns = [];

        while (!empty($chunks)) {
            $chunk = array_shift($chunks);

            $chunkData = [];

            $horizontalArray = [];
            self::toHorizontalArray($chunk, '', $horizontalArray);
            unset($chunk);

            self::toVerticalArray($horizontalArray, $chunkData);
            unset($horizontalArray);

            while (strpos(json_encode($chunkData), 'collection{') !== false) {
                $newData = [];
                foreach ($chunkData as $row) {
                    self::toVerticalArray($row, $newData);
                    unset($row);
                }
                $chunkData = $newData;
                unset($newData);
            }

            foreach ($chunkData as $row) {
                $columns = array_merge($columns, array_keys($row));
            }
            $columns = array_unique($columns);

            $data = array_merge($data, $chunkData);
        }

        $result = [];
        while (!empty($data)) {
            $v = array_shift($data);
            $diff = array_diff($columns, array_keys($v));
            foreach ($diff as $column) {
                $v[$column] = null;
            }
            $result[] = $v;
        }

        return $result;
    }

    protected static function concatKeys(string $k1, $k2): string
    {
        $keys = [];
        if ($k1 !== '') {
            $keys[] = $k1;
        }
        if (is_int($k2)) {
            $keys[] = 'collection{' . $k2 . '}';
        } elseif ($k2 !== '') {
            $keys[] = $k2;
        }

        return implode('.', $keys);
    }

    protected static function toHorizontalArray(array $value, $key, &$result): void
    {
        foreach ($value as $k => $v) {
            $checkName = self::createCheckName(self::concatKeys($key, $k));
            if (!empty(self::$excludedNodes) && in_array($checkName, self::$excludedNodes)) {
                continue;
            }
            if (is_array($v)) {
                if (!empty(self::$keptStringNodes) && in_array($checkName, self::$keptStringNodes)) {
                    $result[self::concatKeys($key, $k)] = json_encode($v);
                    continue;
                }
                self::toHorizontalArray($v, self::concatKeys($key, $k), $result);
            } else {
                $value = $v;
                if ($value === null) {
                    $value = self::$nullValue;
                }
                if ($value === '') {
                    $value = self::$emptyValue;
                }
                $result[self::concatKeys($key, $k)] = $value;
            }
        }
    }

    protected static function toVerticalArray(array $array, &$data): void
    {
        $run = true;
        $i = 0;
        while ($run) {
            $run = false;
            $row = [];
            foreach ($array as $name => $value) {
                $nameParts = [];
                $checkParts = true;
                foreach (explode('.', $name) as $part) {
                    $nameParts[] = $part;
                    if ($checkParts && strpos($part, 'collection{') !== false) {
                        preg_match_all("/^collection\{([0-9]*)\}$/", $part, $matches);
                        $num = (int)$matches[1][0];
                        $checkParts = false;
                        if ($i === $num) {
                            array_pop($nameParts);
                        } elseif ($num > $i) {
                            $run = true;
                            continue 2;
                        } else {
                            continue 2;
                        }
                    }
                }

                $preparedName = implode(".", $nameParts);

                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                if ($value === null && $value !== self::$nullValue) {
                    $value = self::$nullValue;
                }

                if ($value === '' && $value !== self::$emptyValue) {
                    $value = self::$emptyValue;
                }

                $row[$preparedName] = $value;
            }
            $data[] = $row;
            $i++;
        }
    }

    protected static function createCheckName(string $name): string
    {
        $parts = explode('.', $name);

        $arr = [];
        foreach ($parts as $part) {
            if (strpos($part, 'collection{') === false) {
                $arr[] = $part;
            }
        }

        return implode('.', $arr);
    }

    protected static function isAssociative(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected static function configure(array $importPayload): void
    {
        if (!empty($importPayload['rootNode'])) {
            self::$rootNode = $importPayload['rootNode'];
        }

        if (!empty($importPayload['excludedNodes']) && is_array($importPayload['excludedNodes'])) {
            self::$excludedNodes = $importPayload['excludedNodes'];
        }

        if (!empty($importPayload['keptStringNodes']) && is_array($importPayload['keptStringNodes'])) {
            self::$keptStringNodes = $importPayload['keptStringNodes'];
        }

        if (isset($importPayload['nullValue'])) {
            self::$nullValue = $importPayload['nullValue'];
        }
        if (isset($importPayload['data']['configuration'][0]['nullValue'])) {
            self::$nullValue = $importPayload['data']['configuration'][0]['nullValue'];
        }

        if (isset($importPayload['data']['configuration'][0]['emptyValue'])) {
            self::$emptyValue = $importPayload['data']['configuration'][0]['emptyValue'];
        }
        if (isset($importPayload['emptyValue'])) {
            self::$emptyValue = $importPayload['emptyValue'];
        }
    }
}
