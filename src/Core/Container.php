<?php

namespace App\Core;

use Closure;
use InvalidArgumentException;

class Container
{
    /** @var array<string, Closure> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, Closure $factory): void
    {
        $this->definitions[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T
     * @param string $id
     * @return T
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new InvalidArgumentException('Service not defined: ' . $id);
        }

        $this->instances[$id] = ($this->definitions[$id])($this);

        return $this->instances[$id];
    }
}
