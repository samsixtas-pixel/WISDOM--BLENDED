<?php
declare(strict_types=1);

namespace Wisdom\Core;

final class ListQuery
{
    public function __construct(
        public readonly string $sort,
        public readonly string $dir,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $filters,
    ) {
    }

    public static function fromRequest(array $allowedSorts, string $defaultSort = 'id', int $perPage = 20): self
    {
        $sort = Request::get('sort');
        $sort = in_array($sort, $allowedSorts, true) ? $sort : $defaultSort;
        $dir = strtolower(Request::get('dir')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) (Request::get('page') ?: 1));
        $filters = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'f_') && is_string($value) && $value !== '') {
                $filters[substr($key, 2)] = $value;
            }
        }
        return new self($sort, $dir, $page, $perPage, $filters);
    }

    public function filter(string $key, string $default = ''): string
    {
        return is_string($this->filters[$key] ?? null) ? $this->filters[$key] : $default;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}