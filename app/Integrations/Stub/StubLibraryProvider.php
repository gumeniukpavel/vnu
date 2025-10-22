<?php
namespace App\Integrations\Stub;

use App\Integrations\Contracts\LibraryProvider;

final class StubLibraryProvider implements LibraryProvider {
    public function quickSearch(string $query, int $limit = 5): array {
        return [
            ['title'=>"{$query}: вступ",'authors'=>'І. Автор','year'=>'2020','link'=>'#','source'=>'stub'],
            ['title'=>"Поглиблено про {$query}",'authors'=>'П. Дослідник','year'=>'2022','link'=>'#','source'=>'stub'],
        ];
    }
}
