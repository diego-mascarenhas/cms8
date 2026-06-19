<?php

namespace App\DataTables;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PostDataTable extends DataTable
{
    protected ?string $postType = null;

    public function forPostType(?string $postType): self
    {
        $this->postType = $postType;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (Post $post)
            {
                return view('cms.posts.action', compact('post'));
            })
            ->setRowId('id')
            ->editColumn('post_title', function (Post $post)
            {
                $title = $post->post_title !== null && trim($post->post_title) !== ''
                    ? e($post->post_title)
                    : '<span class="text-muted">'.__('app.No title').'</span>';

                return '<a href="'.route('cms.posts.edit', $post->id).'" class="text-body fw-medium">'.$title.'</a>';
            })
            ->editColumn('post_status', function (Post $post)
            {
                $labels = [
                    Post::STATUS_PUBLISH => [__('app.Published'), 'bg-label-success'],
                    Post::STATUS_DRAFT => [__('app.Draft'), 'bg-label-secondary'],
                    Post::STATUS_PENDING => [__('app.Pending'), 'bg-label-warning'],
                    Post::STATUS_FUTURE => [__('app.Scheduled'), 'bg-label-info'],
                    Post::STATUS_PRIVATE => [__('app.Private'), 'bg-label-dark'],
                ];
                [$label, $class] = $labels[$post->post_status] ?? [$post->post_status, 'bg-label-secondary'];

                return '<span class="badge rounded-pill '.$class.'">'.$label.'</span>';
            })
            ->editColumn('post_name', fn (Post $post) => $post->post_name ?: '-')
            ->editColumn('post_modified', function (Post $post)
            {
                return $post->post_modified
                    ? Carbon::parse($post->post_modified)->format('d-m-Y H:i')
                    : '-';
            })
            ->rawColumns(['post_title', 'post_status', 'action']);
    }

    public function query(Post $model): QueryBuilder
    {
        $query = $model->newQuery();

        if ($this->postType !== null)
        {
            $query->where('post_type', $this->postType);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('posts-table')
            ->columns($this->getColumns())
            ->minifiedAjax('', "data.post_type = $('#filter_post_type').val() || '';")
            ->dom('rtip')
            ->orderBy(4, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language([
                'url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json',
                'search' => '',
                'searchPlaceholder' => trans('app.Search'),
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('post_title')->title(trans('app.Title')),
            Column::make('post_name')->title(trans('app.Slug'))->className('text-center'),
            Column::make('post_status')->title(trans('app.Status'))->className('text-center'),
            Column::make('post_modified')->title(trans('app.Modified'))->className('text-center'),
            Column::computed('action')
                ->title(trans('app.Actions'))
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(80),
        ];
    }

    protected function filename(): string
    {
        return 'Posts_'.date('YmdHis');
    }
}
