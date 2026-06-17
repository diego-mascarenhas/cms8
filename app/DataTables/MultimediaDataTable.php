<?php

namespace App\DataTables;

use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use App\Models\Multimedia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MultimediaDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (Multimedia $multimedia)
            {
                return view('multimedia.action', compact('multimedia'));
            })
            ->setRowId('id')
            ->addColumn('preview', function (Multimedia $multimedia)
            {
                $previewUrl = $multimedia->getFirstMediaUrl('poster');
                if (! $previewUrl)
                {
                    $previewUrl = $multimedia->getFirstMediaUrl('media', 'poster');
                }
                if (! $previewUrl)
                {
                    $previewUrl = $multimedia->getFirstMediaUrl('media', 'thumb');
                }
                if (! $previewUrl)
                {
                    $previewUrl = $multimedia->getFirstMediaUrl('media');
                }

                if ($previewUrl)
                {
                    return '<div class="avatar me-3"><img src="'.$previewUrl.'" class="rounded" alt="'.$multimedia->title.'"></div>';
                }

                $icon = $this->getTypeIcon($multimedia->type);

                return '<div class="avatar me-3"><span class="avatar-initial rounded bg-label-secondary"><i class="'.$icon.'"></i></span></div>';
            })
            ->editColumn('category_id', function (Multimedia $multimedia)
            {
                return $multimedia->category
                    ? $multimedia->category->name
                    : '<span class="text-muted">'.__('No category').'</span>';
            })
            ->editColumn('type', function (Multimedia $multimedia)
            {
                return __(ucfirst($multimedia->type));
            })
            ->editColumn('status', function (Multimedia $multimedia)
            {
                $label = $multimedia->status?->label() ?? __('Unknown');
                $class = $multimedia->status === MultimediaStatus::ACTIVE ? 'bg-label-success' : 'bg-label-secondary';

                return '<span class="badge rounded-pill '.$class.'">'.$label.'</span>';
            })
            ->editColumn('visibility', function (Multimedia $multimedia)
            {
                $label = $multimedia->visibility?->label() ?? __('Unknown');
                $class = $multimedia->visibility === MultimediaVisibility::PUBLIC ? 'bg-label-info' : 'bg-label-secondary';

                return '<span class="badge rounded-pill '.$class.'">'.$label.'</span>';
            })
            ->addColumn('tags', function (Multimedia $multimedia)
            {
                $tags = $multimedia->tags->where('type', 'general');
                if ($tags->isEmpty())
                {
                    return '<span class="text-muted">'.__('No tags').'</span>';
                }

                return $tags->map(function ($tag)
                {
                    return '<span class="badge bg-label-info me-1">'.$tag->name.'</span>';
                })->implode(' ');
            })
            ->editColumn('created_at', function (Multimedia $multimedia)
            {
                return $multimedia->created_at
                    ? Carbon::parse($multimedia->created_at)->format('d-m-Y H:i')
                    : '-';
            })
            ->rawColumns(['preview', 'category_id', 'status', 'visibility', 'tags', 'action']);
    }

    public function query(Multimedia $model): QueryBuilder
    {
        $query = $model->newQuery()->with(['category', 'tags', 'media']);
        $request = $this->request();

        if ($request->filled('category_id'))
        {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->filled('status'))
        {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('visibility'))
        {
            $query->where('visibility', $request->get('visibility'));
        }

        if ($request->filled('type'))
        {
            $query->where('type', $request->get('type'));
        }

        // Search filter
        $search = $request->input('search.value', $request->input('search'));
        if (is_string($search) && $search !== '')
        {
            $query->where(function ($q) use ($search)
            {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by tags array (from multi-select)
        if ($request->filled('tags') && is_array($request->get('tags')))
        {
            $tags = array_filter($request->get('tags'));
            if (! empty($tags))
            {
                $locale = app()->getLocale();
                $query->whereHas('tags', function ($tagQuery) use ($tags, $locale)
                {
                    $tagQuery->whereIn("name->{$locale}", $tags)
                        ->where('type', 'general');
                });
            }
        }

        // Filter by galleries array (from multi-select)
        if ($request->filled('galleries') && is_array($request->get('galleries')))
        {
            $galleries = array_filter($request->get('galleries'));
            if (! empty($galleries))
            {
                $locale = app()->getLocale();
                $query->whereHas('tags', function ($tagQuery) use ($galleries, $locale)
                {
                    $tagQuery->whereIn("name->{$locale}", $galleries)
                        ->where('type', 'gallery');
                });
            }
        }

        if ($request->filled('tag_id'))
        {
            $tagId = $request->get('tag_id');
            $query->whereHas('tags', function ($tagQuery) use ($tagId)
            {
                $tagQuery->where('tags.id', $tagId)
                    ->where('tags.type', 'general');
            });
        }

        if ($request->filled('gallery_tag_id'))
        {
            $tagId = $request->get('gallery_tag_id');
            $query->whereHas('tags', function ($tagQuery) use ($tagId)
            {
                $tagQuery->where('tags.id', $tagId)
                    ->where('tags.type', 'gallery');
            });
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('multimedia-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(8, 'desc')
            ->responsive(true)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json']);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::computed('preview')
                ->title(__('app.Preview'))
                ->orderable(false)
                ->searchable(false),
            Column::make('title')->title(__('app.Title')),
            Column::make('type')
                ->title(__('app.Type'))
                ->className('text-center'),
            Column::make('category_id')
                ->title(__('app.Category'))
                ->className('text-center')
                ->orderable(false)
                ->searchable(false),
            Column::make('status')
                ->title(__('app.Status'))
                ->className('text-center'),
            Column::make('visibility')
                ->title(__('app.Visibility'))
                ->className('text-center'),
            Column::computed('tags')
                ->title(__('app.Tags'))
                ->orderable(false)
                ->searchable(false),
            Column::make('created_at')
                ->title(__('app.Created'))
                ->className('text-center'),
            Column::computed('action')
                ->title(__('app.Actions'))
                ->width(20)
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'Multimedia_'.date('YmdHis');
    }

    private function getTypeIcon(string $type): string
    {
        return match ($type)
        {
            'image' => 'ti ti-photo',
            'video' => 'ti ti-video',
            'audio' => 'ti ti-music',
            default => 'ti ti-file',
        };
    }
}
