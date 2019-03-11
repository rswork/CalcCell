<?php

namespace Rswork\Component;

use Rswork\Component\Exception\BaseException;

class CalcCell
{
    const TYPE_INT = 'int';

    const TYPE_FLOAT = 'float';

    const TYPE_STRING = 'string';

    const TYPE_CELL = 'cell';

    const TYPE_ARRAY = 'array';

    const TYPE_BOOL = 'bool';

    const TYPE_OTHER = '';

    protected $structure = [];

    protected $refs = [];

    protected $data = [];

    protected $null;

    public function __construct(array $columns = [])
    {
        foreach ($columns as $column => $type) {
            $this->structure[$column] = $type;
        }
    }

    public function getStructure()
    {
        return $this->structure;
    }

    public function &__get($column)
    {
        if (isset($this->structure[$column])) {
            if (!array_key_exists($column, $this->data)) {
                $this->__set($column, null);
            }

            return $this->data[$column];
        }

        return $this->null;
    }

    public function __set($column, $value)
    {
        if (!isset($this->structure[$column])) {
            throw new BaseException('invalid CalcCell Column: '.$column, BaseException::INVALID_ARGUMENT);
        }

        switch ($this->structure[$column]) {
            case self::TYPE_INT:
                if (!array_key_exists($column, $this->data)) {
                    $this->data[$column] = 0;
                }

                $value = (int)$value;
                break;

            case self::TYPE_FLOAT:
                if (!array_key_exists($column, $this->data)) {
                    $this->data[$column] = 0.0;
                }

                $value = (float)$value;
                break;

            case self::TYPE_STRING:
                if (!array_key_exists($column, $this->data)) {
                    $this->data[$column] = '';
                }

                $value = (string)$value;
                break;

            case self::TYPE_ARRAY:
                if (!array_key_exists($column, $this->data)) {
                    $this->data[$column] = [];
                }

                if (!is_array($value)) {
                    $value = [];
                }
                break;

            case self::TYPE_CELL:
            case self::TYPE_OTHER:
            case self::TYPE_BOOL:
            default:
                if (!array_key_exists($column, $this->data)) {
                    $this->data[$column] = null;
                }
        }

        $old = $this->data[$column];
        $this->data[$column] = $value;

        if (isset($this->refs[$column])) {
            foreach ($this->refs[$column] as $refInfo) {
                call_user_func(
                    $refInfo['callback'],
                    $old,
                    $value,
                    $refInfo['ref'],
                    $refInfo['refColumn'],
                    $this
                );
            }
        }
    }

    public function __isset($column)
    {
        return isset($this->structure[$column]);
    }

    public function get($column)
    {
        return $this->__get($column);
    }

    public function set($column, $value)
    {
        $this->__set($column, $value);

        return $this;
    }

    public function append($arrayColumn, $item, $key = null)
    {
        if (!isset($this->structure[$arrayColumn]) || $this->structure[$arrayColumn] !== self::TYPE_ARRAY) {
            throw new BaseException('invalid Calc Cell Column: '.$arrayColumn, BaseException::INVALID_ARGUMENT);
        }

        $value = $this->get($arrayColumn) ?: [];

        if (!$key) {
            $value[] = $item;
        } else {
            $value[$key] = $item;
        }

        $this->set($arrayColumn, $value);

        return $this;
    }

    public function uniqueAppend($arrayColumn, $item, $key = null, $override = false)
    {
        if (!isset($this->structure[$arrayColumn]) || $this->structure[$arrayColumn] !== self::TYPE_ARRAY) {
            throw new BaseException('invalid Calc Cell Column: '.$arrayColumn, BaseException::INVALID_ARGUMENT);
        }

        $value = $this->get($arrayColumn) ?: [];
        $needSet = false;

        if (!$key) {
            if (!in_array($item, $value)) {
                $value[] = $item;
                $needSet = true;
            }
        } elseif ($override || !isset($value[$key])) {
            $value[$key] = $item;
            $needSet = true;
        }

        if ($needSet) {
            $this->set($arrayColumn, $value);
        }

        return $this;
    }

    public function refCallback($column, CalcCell $ref, $refColumn, callable $callback)
    {
        if (!isset($this->refs[$column])) {
            $this->refs[$column] = [];
        }

        $this->refs[$column][] = [
            'ref' => $ref,
            'refColumn' => $refColumn,
            'callback' => $callback,
        ];

        return $this;
    }

    public function refAdd($column, CalcCell $ref, $refColumn)
    {
        return $this->refCallback($column, $ref, $refColumn, function($old, $new, $ref, $refColumn, $cell) {
            $ref->{$refColumn} = $new - $old + $ref->{$refColumn};
        });
    }

    public function refMultiply($column, CalcCell $ref, $refColumn)
    {
        return $this->refCallback($column, $ref, $refColumn, function($old, $new, $ref, $refColumn, $cell) {
            $ref->{$refColumn} = $ref->{$refColumn} * ($new - $old);
        });
    }

    public function toArray()
    {
        $array = [];

        foreach ($this->structure as $column => $type) {
            if (!array_key_exists($column, $this->data)) {
                $this->__get($column);
            }

            $array[$column] = $this->data[$column];

            if ($array[$column] instanceof self) {
                $array[$column] = $array[$column]->toArray();
            }

            if ($type === self::TYPE_ARRAY) {
                foreach ($array[$column] as $key => $value) {
                    if ($value instanceof self) {
                        $array[$column][$key] = $value->toArray();
                    }
                }
            }
        }

        return $array;
    }
}
