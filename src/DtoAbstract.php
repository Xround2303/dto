<?php

namespace App\DTO;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;


abstract class DTO
{
    /**
     * @param array|object $data
     * @return static
     * @throws ReflectionException
     */
    public static function fromArray($data): DTO
    {
        $dto = new (static::class)();
        $reflectionClass = new ReflectionClass($dto);

        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                self::fillDto($reflectionClass, $dto, $key, $value);
            }
        }

        return $dto;
    }

    protected static function fillDto($reflectionClass, $dto, $key, $value)
    {
        $method = Str::ucfirst(Str::camel($key));
        $method = "set{$method}";

        if (method_exists($dto, $method)) {
            $dto->$method($value);
        }
        else {
            $property = $reflectionClass->getProperty($key);
            $type = $property->getType();

            if (is_subclass_of($type->getName(), self::class)) {
                $dto->$key = ($type->getName())::fromArray($value);
            } elseif ($type->getName() == "array" AND $dtoClass = self::findClassDtoArray($property)) {
                $dto->$key = array_map(fn($item) => $dtoClass::fromArray($item), $value);
            } else {
                $dto->$key = $value;
            }
        }
    }

    protected static function findClassDtoArray(ReflectionProperty $property)
    {
        if(!$property->getType()->getName() == "array") {
            return false;
        }

        if(!preg_match('/@var\s+([\w\\\\]+)(\[])?/', $property->getDocComment(), $matches) ) {
            return false;
        }

        $itemType = trim($matches[1]);

        if(!class_exists($itemType) OR !is_subclass_of($itemType, self::class)) {
            return false;
        }

        return $itemType;
    }


    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            $method = Str::ucfirst(Str::camel($key));
            $method = "get{$method}";
            if (method_exists($this, $method)) {
                $result[$key] = $this->$method();
            } else {
                if(is_subclass_of($value, self::class)) {
                    $result[$key] = $value->toArray();
                }
                else if(is_array($value)) {
                    $rows = [];
                    foreach ($value as $item) {
                        if(is_subclass_of($item, self::class)) {
                            $rows[] = $item->toArray();
                        }
                    }

                    $result[$key] = $rows;
                }
                else {
                    $result[$key] = $value;
                }

            }
        }
        return $result;
    }

    public function toObject(): object
    {
        return json_decode(json_encode($this));
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    public function __unserialize(array $data)
    {
        $this->fromArray($data);
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Превращаем все элементы массива в текущий DTO
     * @return static[]
     * @throws ReflectionException
     */
    public static function fromList(array $list): array
    {
        foreach ($list as $item) {
            $rows[] = self::fromArray($item);
        }

        return $rows ?? [];
    }
}