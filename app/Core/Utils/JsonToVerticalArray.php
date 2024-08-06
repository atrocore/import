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
    private static ?array $importPayload;

    public static function mutate(string $json, ?array $importPayload = null): array
    {
        self::$importPayload = $importPayload;

        $array = @json_decode($json, true);
        if (empty($array)) {
            return [];
        }

        $horizontalArray = [];
        self::toHorizontalArray($array, '', $horizontalArray);

        $data = [];
        self::toVerticalArray($horizontalArray, $data);

        while (strpos(json_encode($data), 'collection{') !== false) {
            $newData = [];
            foreach ($data as $row) {
                self::toVerticalArray($row, $newData);
            }
            $data = $newData;
        }

        $keys = [];
        foreach ($data as $row) {
            $keys = array_merge($keys, array_keys($row));
        }
        $keys = array_unique($keys);

        $result = [];
        foreach ($data as $v) {
            $row = [];
            foreach ($keys as $key) {
                $row[$key] = isset($v[$key]) ? $v[$key] : null;
            }
            $result[] = $row;
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
        $nullValue = null;
        $emptyValue = '';

        /**
         * Prepare NULL value and EMPTY value
         */
        $importPayload = self::$importPayload;
        if (!empty($importPayload) && is_array($importPayload)) {
            if (isset($importPayload['data']['configuration'][0]['nullValue'])) {
                $nullValue = $importPayload['data']['configuration'][0]['nullValue'];
            }
            if (isset($importPayload['data']['configuration'][0]['emptyValue'])) {
                $emptyValue = $importPayload['data']['configuration'][0]['emptyValue'];
            }
        }

        foreach ($value as $k => $v) {
            $checkName = self::createCheckName(self::concatKeys($key, $k));
            if (!empty($importPayload['data']['excludedNodes']) && is_array($importPayload['data']['excludedNodes'])) {
                if (in_array($checkName, $importPayload['data']['excludedNodes'])) {
                    continue;
                }
            }
            if (is_array($v)) {
                if (!empty($importPayload['data']['keptStringNodes']) && is_array($importPayload['data']['keptStringNodes'])) {
                    if (in_array($checkName, $importPayload['data']['keptStringNodes'])) {
                        $result[self::concatKeys($key, $k)] = json_encode($v);
                        continue;
                    }
                }
                self::toHorizontalArray($v, self::concatKeys($key, $k), $result);
            } else {
                if ($v === null) {
                    $v = $nullValue;
                }
                if ($v === '') {
                    $v = $emptyValue;
                }

                $result[self::concatKeys($key, $k)] = $v;
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

                // treat leaf collection as single array
                if (!empty($leaf = end($nameParts) ?: '') && preg_match("/^collection\{([0-9]*)\}$/", $leaf)) {
                    array_pop($nameParts);
                }

                $preparedName = implode(".", $nameParts);

                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                if (!empty($row[$preparedName])) {
                    $existing = $row[$preparedName];
                    if (!is_array($existing)) {
                        $existing = [$row[$preparedName]];
                    }
                    $existing[] = $value;
                    $row[$preparedName] = $existing;
                } else {
                    $row[$preparedName] = $value;
                }
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
}
