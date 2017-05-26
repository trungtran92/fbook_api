<?php

namespace App\Contracts\Repositories;

use App\Eloquent\Book;

interface BookRepository extends AbstractRepository
{
    public function getDataInHomepage($with = [], $dataSelect = ['*']);

    public function getBooksByFields($with = [], $dataSelect = ['*'], $field);

    public function booking(Book $book, array $data);

}
