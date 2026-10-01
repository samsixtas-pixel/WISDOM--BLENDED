<?php
declare(strict_types=1);

namespace Wisdom\Contracts;

/** Contract every repository must honour (abstraction). */
interface RepositoryInterface
{
    public function find(int $id): ?object;

    public function delete(int $id): bool;

    public function count(): int;
}
