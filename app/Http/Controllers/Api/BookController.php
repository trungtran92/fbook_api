<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\BookRepository;
use App\Exceptions\Api\ActionException;
use App\Http\Requests\Api\Book\IndexRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\Api\Book\BookingRequest;
use App\Eloquent\Book;

class BookController extends ApiController
{
    public function __construct(BookRepository $repository)
    {
        parent::__construct($repository);
    }

    protected $select = [
        'id',
        'title',
        'description',
        'author',
        'publish_date',
        'total_page',
        'avg_star',
        'count_view',
        'status',
    ];

    public function index(IndexRequest $request)
    {
        $field = $request->input('field');
        if (!$field) {
            throw new ActionException;
        }

        $relations = ['image'];

        return $this->getData(function () use ($relations, $field) {
            $data = $this->repository->getBooksByFields($relations, $this->select, $field);

            $this->compacts['item'] = $this->reFormatPaginate($data);
        });
    }

    protected function reFormatPaginate(LengthAwarePaginator $paginate)
    {
        $currentPage = $paginate->currentPage();

        return [
            'total' => $paginate->total(),
            'per_page' => $paginate->perPage(),
            'current_page' => $currentPage,
            'next_page' => ($paginate->lastPage() > $currentPage) ? $currentPage + 1 : null,
            'prev_page' => ($currentPage > 1) ? $currentPage - 1 : null,
            'data' => $paginate->items(),
        ];
    }

    public function show($id)
    {
        parent::show($id);

        $this->compacts['item']->load(['image', 'reviewsDetailBook',
            'userReadingBook' => function ($query) {
                $query->select('id', 'name', 'avatar');
            },
            'usersWaitingBook' => function($query) {
                $query->select('id', 'name', 'avatar');
                $query->orderBy('book_user.created_at', 'ASC');
            },
            'category' => function($query) {
                $query->select('id', 'name');
            },
            'office' => function($query) {
                $query->select('id', 'name');
            },
            'owner' => function($query) {
                $query->select('id', 'name');
            },
        ]);

      return $this->jsonRender();
    }

    public function booking(BookingRequest $request, $id)
    {
        $data = $request->all();
        return $this->doAction(function () use ($data, $id) {
            $book = $this->repository->findOrfail($id);

             $this->repository->booking($book, $data);
        });
    }

}
