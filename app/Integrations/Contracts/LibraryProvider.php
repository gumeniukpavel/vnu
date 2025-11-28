<?php
namespace App\Integrations\Contracts;

interface LibraryProvider {
    public function quickSearch(string $query, int $limit = 5): array;
}
