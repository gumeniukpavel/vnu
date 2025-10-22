<?php
namespace App\Integrations\Contracts;

interface LibraryProvider {
    /** @return array<array{title:string,authors?:string,year?:string,link:string,source:string}> */
    public function quickSearch(string $query, int $limit = 5): array;
}
