<?php

namespace App\Repositories;

use App\Contracts\Repositories\BookRepository;
use App\Eloquent\Book;
use App\Eloquent\BookUser;
use App\Eloquent\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class BookRepositoryEloquent extends AbstractRepositoryEloquent implements BookRepository
{
    public function model()
    {
        return new \App\Eloquent\Book;
    }

    public function getDataInHomepage($with = [], $dataSelect = ['*'])
    {
        $limit = config('paginate.book_home_limit');

        /**
         * keys must match with config:
         * - view
         * - waiting
         * - rating
         * - latest
         */
        return [
            [
                'key' => 'latest',
                'title' => translate('title_key.latest'),
                'data' => $this->getLatestBooks($with, $dataSelect, $limit)->items(),
            ],
            [
                'key' => 'view',
                'title' => translate('title_key.view'),
                'data' => $this->getBooksByCountView($with, $dataSelect, $limit)->items(),
            ],
            [
                'key' => 'rating',
                'title' => translate('title_key.rating'),
                'data' => $this->getBooksByRating($with, $dataSelect, $limit)->items(),
            ],
            [
                'key' => 'waiting',
                'title' => translate('title_key.waiting'),
                'data' => $this->getBooksByWaiting($with, $dataSelect, $limit)->items(),
            ],
        ];
    }

    protected function getLatestBooks($with = [], $dataSelect = ['*'], $limit = '')
    {
        return $this->model()
            ->select($dataSelect)
            ->with($with)
            ->getData('created_at')
            ->paginate($limit ?: config('paginate.default'));
    }

    protected function getBooksByCountView($with = [], $dataSelect = ['*'], $limit = '')
    {
        return $this->model()
            ->select($dataSelect)
            ->with($with)
            ->getData('count_view')
            ->paginate($limit ?: config('paginate.default'));
    }

    protected function getBooksByRating($with = [], $dataSelect = ['*'], $limit = '')
    {
        return $this->model()
            ->select($dataSelect)
            ->with($with)
            ->getData('avg_star')
            ->paginate($limit ?: config('paginate.default'));
    }

    protected function getBooksByWaiting($with = [], $dataSelect = ['*'], $limit = '')
    {
        $numberOfUserWaitingBook = \DB::table('books')
            ->join('book_user', 'books.id', '=', 'book_user.book_id')
            ->select('book_user.book_id', \DB::raw('count(book_user.user_id) as count_waiting'))
            ->where('book_user.status', Book::STATUS['waiting'])
            ->groupBy('book_user.book_id')
            ->orderBy('count_waiting', 'DESC')
            ->limit($limit ?: config('paginate.default'))
            ->get();

        $books = $this->model()
            ->select($dataSelect)
            ->with($with)
            ->whereIn('id', $numberOfUserWaitingBook->pluck('book_id')->toArray())
            ->paginate($limit ?: config('paginate.default'));

        foreach ($books->items() as $book) {
            $book->count_waiting = $numberOfUserWaitingBook->where('book_id', $book->id)->first()->count_waiting;
        }

        return $books;
    }

    public function getBooksByFields($with = [], $dataSelect = ['*'], $field)
    {
        switch ($field) {
            case 'view':
                return $this->getBooksByCountView($with, $dataSelect);

            case 'latest':
                return $this->getLatestBooks($with, $dataSelect);

            case 'rating':
                return $this->getBooksByRating($with, $dataSelect);

            case 'waiting':
                return $this->getBooksByWaiting($with, $dataSelect);
        }
    }

    public function booking(Book $book, array $attributes)
    {
        $bookUpdate = array_only($attributes['item'], app(BookUser::class)->getFillable());
        $currentStatus = $book->users()->find($bookUpdate['user_id']);

        if ($currentStatus->pivot->status == config('model.book_status.waiting')) {
            $book->update(['status' => 'available']);
            $book->userReadingBook()->updateExistingPivot($bookUpdate['user_id'], ['status' => config('model.book_status.done')]);

        } else {
            $userWaiting = $book->usersWaitingBook()
                ->where('user_id', '<>', $bookUpdate['user_id'])
                ->count();

            if ($userWaiting) {
                if (!$currentStatus) {
                    $book->users()->attach($bookUpdate['user_id'], [
                        'user_id' => $bookUpdate['user_id'],
                        'book_id' => $bookUpdate['book_id'],
                        'status' => config('model.book_status.waiting')
                    ]);
                } else {
                    $book->users()->updateExistingPivot($bookUpdate['user_id'], ['status' => config('model.book_status.waiting')]);
                }
            } else {
                $book->users()->updateExistingPivot($bookUpdate['user_id'], ['book_user.status' => config('model.book_status.reading')]);
                $book->where('id', $bookUpdate['book_id'])->update(['status' => 'unavailable']);
            }
        }

    }

}
