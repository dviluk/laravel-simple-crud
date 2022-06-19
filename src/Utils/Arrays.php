<?php

namespace Dviluk\LaravelSimpleCrud\Utils;

class Arrays
{
    public static function idAsIndex($collection, $columnId = 'id')
    {
        $formatted = [];

        foreach ($collection as $item) {
            $id = $item[$columnId];

            $formatted[$id] = $item;
        }

        return $formatted;
    }

    public static function groupItemsByIndex($collection, $columnId, $isStdClass = false)
    {
        $formatted = [];

        if ($isStdClass) {
            foreach ($collection as $item) {
                $id = $item->{$columnId};

                if (isset($formatted[$id])) {
                    $formatted[$id][] = $item;
                } else {
                    $formatted[$id] = [$item];
                }
            }
        } else {
            foreach ($collection as $item) {
                $id = $item[$columnId];

                if (isset($formatted[$id])) {
                    $formatted[$id][] = $item;
                } else {
                    $formatted[$id] = [$item];
                }
            }
        }

        return $formatted;
    }

    public static function groupIndexByAnotherIndex($array, $indexToGroup, $indexAsGroup)
    {
        $itemsGrouped = [];

        foreach ($array as $arr) {
            $groupId = $arr[$indexAsGroup];
            $id = $arr[$indexToGroup];

            if (array_key_exists($groupId, $itemsGrouped)) {
                $itemsGrouped[$groupId][] = $id;
            } else {
                $itemsGrouped[$groupId] = [$id];
            }
        }

        return $itemsGrouped;
    }

    public static function invertValuesGroupedByIndex($array, $indexName)
    {
        $itemsGrouped = [];

        foreach ($array as $index => $values) {
            foreach ($values as $value) {
                $itemsGrouped[$value] = [
                    $indexName => $index
                ];
            }
        }

        return $itemsGrouped;
    }

    /**
     * Extrae el id pivote y lo usa como key de los elementos.
     * 
     * @param array $array 
     * @param string $pivotKey 
     * @param null|array $attachExtraData 
     * @return array 
     */
    public static function formatPivotData(array $array, string $pivotKey = 'id', ?array $attachExtraData = null)
    {
        $data = [];

        foreach ($array as $item) {
            $key = $item[$pivotKey];

            unset($item[$pivotKey]);

            if ($attachExtraData) {
                $item = array_merge($item, $attachExtraData);
            }

            $data[$key] = $item;
        }

        return $data;
    }

    public static function idAsIndexForCheck($collection, $columnId = 'id')
    {
        $formatted = [];

        foreach ($collection as $item) {
            $id = $item[$columnId];

            $formatted[$id] = true;
        }

        return $formatted;
    }

    public static function valueAsIndex($collection, $val = null)
    {
        $formatted = [];

        foreach ($collection as $item) {
            $formatted[$item] = $val ?? $item;
        }

        return $formatted;
    }
    /**
     * Los valores del arreglo se convierten en keys y se le asigna el valor retornado
     * por el closure.
     * 
     * @param array $array 
     * @param \Closure $attach 
     * @return array 
     */
    public static function arrayValuesAsKeysWithData(array $array, \Closure $attach)
    {
        $pivot = [];

        foreach ($array as $item) {
            $pivot[$item] = $attach($item);
        }

        return $pivot;
    }

    /**
     * Retorna un arreglo sin los keys especificados.
     * 
     * @param array $array 
     * @param array $keysToOmit 
     * @return array 
     */
    public static function omitKeys(array $array, array $keysToOmit)
    {
        if (count($keysToOmit) === 1) {
            unset($array[$keysToOmit[0]]);

            return $array;
        }

        return array_diff_key($array, array_flip($keysToOmit));
    }

    /**
     * Retorna un arreglo con solo los elementos de los keys especificado.
     * 
     * @param array $array 
     * @param array $keysToPreserve 
     * @return array 
     */
    public static function preserveKeys(array $array, array $keysToPreserve)
    {
        return array_intersect_key($array, array_flip($keysToPreserve));
    }

    /**
     * Retorna un arreglo sin los valores especificados.
     * 
     * @param array $array 
     * @param array $valuesToOmit 
     * @return array 
     */
    public static function omitValues(array $array, array $valuesToOmit)
    {
        return array_diff($array, $valuesToOmit);
    }


    /**
     * Retorna un arreglo con el valor del indice especificado.
     * 
     * @param array $array 
     * @param string $key 
     * @return array 
     */
    public static function toArrayOfValues(array $array, string $key)
    {
        $data = [];

        foreach ($array as $item) {
            $data[] = $item[$key];
        }

        return $data;
    }

    /**
     * Retorna un arreglo con los items a creados, actualizados y eliminados.
     * 
     * @param array $newItems Data de los elementos a calcular.
     * @param array $currentItemsIds Ids de los elementos actuales.
     * @param string $key Indica de que key se sacara el Id de los elementos
     * @return array 
     * 
     * - (array)    `create`: array $key => $data de los elementos a crear
     * - (array)    `update`: array $key => $data de los elementos a actualizar
     * - (array)    `delete`: contiene solo los keys de los elementos a eliminar
     */
    public static function getChangesOfData(array $newItems, array $currentItemsIds, string $key = 'id')
    {
        // Se le da formato al arreglo para su fácil manipulación
        $newItemsById = self::formatPivotData($newItems);
        $newItemsIds = self::toArrayOfValues($newItems, $key);

        // Se identifican los elementos a eliminar
        // Se toma la diferencia que hay entre los ids que existen en la DB y no en `$newItemsIds`
        $itemsToDelete = array_diff($currentItemsIds, $newItemsIds);

        // Se identifican los elementos a eliminar
        // Se omiten todos los elementos que existen en la DB y los que se van a eliminar
        $itemsToCreate = self::omitKeys($newItemsById, array_merge($currentItemsIds, $itemsToDelete));

        // Se identifican los elementos a actualizar
        // Se omiten los elementos que se van a crear y a eliminar
        $itemsToUpdate = self::omitKeys($newItemsById, array_merge($itemsToDelete, array_keys($itemsToCreate)));

        return [
            'create' => $itemsToCreate,
            'update' => $itemsToUpdate,
            'delete' => $itemsToDelete,
        ];
    }
}
