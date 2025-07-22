<?php

namespace App\DataTables;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CollaboratorDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($contact)
            {
                return view('collaborator.action', compact('contact'));
            })
            ->addColumn('rating', function ($contact)
            {
                // Get the valoration name from the relationship
                if (!$contact->valoration) {
                    return ''; // Return empty string if no valoration
                }

                $valoration = $contact->valoration->name;

                switch ($valoration)
                {
                    case 'Top':
                        return '<div class="d-flex align-items-center justify-content-center"><i class="ti ti-star-filled text-warning ti-sm me-2"></i> Top</div>';
                    case 'Lista negra':
                        return '<div class="d-flex align-items-center justify-content-center"><i class="ti ti-x text-danger ti-sm me-2"></i> Lista negra</div>';
                    case 'Validada':
                        return '<div class="d-flex align-items-center justify-content-center"><i class="ti ti-check text-success ti-sm me-2"></i> Validada</div>';
                    case 'Ojo':
                        return '<div class="d-flex align-items-center justify-content-center"><i class="ti ti-eye text-warning ti-sm me-2"></i> Ojo</div>';
                    case 'Interesante':
                        return '<div class="d-flex align-items-center justify-content-center"><i class="ti ti-clock text-info ti-sm me-2"></i> Interesante</div>';
                    default:
                        return ''; // Return empty string for unknown valorations
                }
            })
                                                                                    ->addColumn('language_combinations', function ($contact)
            {
                // Get language combinations from the contact's language variants
                $combinations = [];

                if ($contact->languageVariants && $contact->languageVariants->count() > 0)
                {
                    foreach ($contact->languageVariants as $variant)
                    {
                        $combinations[] = '<div class="language-combination" style="text-align: center;">' . $variant->source_language_code . ' → ' . $variant->target_language_code . '</div>';
                    }
                }

                return empty($combinations) ? '' : implode('', $combinations);
            })
            ->addColumn('services', function ($contact)
            {
                // Get unique services from the contact's fares (group by fare_id)
                $services = [];

                if ($contact->fares && $contact->fares->count() > 0)
                {
                    $uniqueFares = $contact->fares->unique('id');
                    foreach ($uniqueFares as $fare)
                    {
                        $serviceName = $fare->name;
                        if ($fare->type)
                        {
                            $serviceName .= ' (' . $fare->type->name . ')';
                        }
                        $services[] = $serviceName;
                    }
                }

                if (empty($services))
                {
                    return '';
                }

                $count = count($services);
                $servicesList = implode(', ', $services);
                $label = $count === 1 ? 'servicio' : 'servicios';

                return '<span class="badge bg-label-info rounded-pill" title="' . htmlspecialchars($servicesList) . '" data-bs-toggle="tooltip" data-bs-placement="auto">' . $count . ' ' . $label . '</span>';
            })
            ->orderColumn('services', function ($query, $order)
            {
                // Count unique fares (services) for proper ordering
                $query->withCount(['fares as unique_fares_count' => function ($q)
                {
                    $q->selectRaw('COUNT(DISTINCT fare_id)');
                }])->orderBy('unique_fares_count', $order);
            })
            ->addColumn('projects', function ($contact)
            {
                // Get the actual count of projects for this collaborator
                $projectCount = $contact->projects_count ?? $contact->projects->count();

                return '<span class="badge bg-label-primary rounded-pill">' . $projectCount . '</span>';
            })
            ->orderColumn('projects', function ($query, $order)
            {
                $query->orderBy('projects_count', $order);
            })
            ->setRowId('id')
            ->filterColumn('language_combinations', function ($query, $keyword)
            {
                if ($keyword !== '')
                {
                    // Filter by source or target language variant code
                    $query->whereHas('languageVariants', function ($q) use ($keyword)
                    {
                        $q->where('source_language_code', $keyword)
                            ->orWhere('target_language_code', $keyword);
                    });
                }
            })
            ->filterColumn('services', function ($query, $keyword)
            {
                if ($keyword !== '')
                {
                    // Filter by fare ID
                    $query->whereHas('fares', function ($q) use ($keyword)
                    {
                        $q->where('fare_id', $keyword);
                    });
                }
            })
            ->rawColumns(['name', 'action', 'rating', 'language_combinations', 'services', 'projects']);
    }

    public function query(Contact $model): QueryBuilder
    {
        $query = $model->newQuery()
            ->with(['valoration', 'languageVariants.sourceLanguage', 'languageVariants.targetLanguage', 'fares.type', 'weeklyAvailability'])
            ->withCount([
                'projects',
                'fares as unique_fares_count' => function ($q)
                {
                    $q->selectRaw('COUNT(DISTINCT fare_id)');
                },
            ]);

        // Handle custom filters from request
        $request = request();

        // Handle dashboard filters
        if ($request->has('dashboard_filter') && $request->dashboard_filter)
        {
            $query = $this->applyDashboardFilter($query, $request->dashboard_filter);
        }

        // Filter by source language (base or variant)
        if ($request->has('source_language') && $request->source_language)
        {
            $source = $request->source_language;
            if (strlen($source) === 2)
            {
                // If base language (2-letter), match all variants as source
                $query->whereHas('languageVariants', function ($q) use ($source)
                {
                    $q->where('source_language_code', 'like', $source . '%')
                        ->orWhere('source_language_code', $source);
                });
            }
            else
            {
                // If exact variant, match only that as source
                $query->whereHas('languageVariants', function ($q) use ($source)
                {
                    $q->where('source_language_code', $source);
                });
            }
        }

        // Filter by target language (base or variant)
        if ($request->has('target_language') && $request->target_language)
        {
            $target = $request->target_language;
            if (strlen($target) === 2)
            {
                // If base language (2-letter), match all variants as target
                $query->whereHas('languageVariants', function ($q) use ($target)
                {
                    $q->where('target_language_code', 'like', $target . '%')
                        ->orWhere('target_language_code', $target);
                });
            }
            else
            {
                // If exact variant, match only that as target
                $query->whereHas('languageVariants', function ($q) use ($target)
                {
                    $q->where('target_language_code', $target);
                });
            }
        }

        // Filter by service/fare
        if ($request->has('service') && $request->service)
        {
            $query->whereHas('fares', function ($q) use ($request)
            {
                $q->where('fares.id', $request->service);
            });
        }

        // Filter by availability (days and delivery date)
        if (($request->has('days') && $request->days) && ($request->has('delivery_date') && $request->delivery_date))
        {
            // Only apply filter if BOTH days and delivery_date are provided
            $query = $this->applyAvailabilityFilter($query, $request);
        }

        return $query;
    }

    /**
     * Apply availability filter based on days and delivery date
     */
    private function applyAvailabilityFilter(QueryBuilder $query, $request)
    {
        $days = $request->days ? (int) $request->days : null;
        $deliveryDate = null;

        // Parse delivery date filter - now handles both old format and new date format
        if ($request->delivery_date)
        {
            // Check if it's the old format (predefined options)
            switch ($request->delivery_date)
            {
                case 'today':
                    $deliveryDate = now()->format('Y-m-d');
                    break;
                case '1_week':
                    $deliveryDate = now()->addWeek()->format('Y-m-d');
                    break;
                case '15_days':
                    $deliveryDate = now()->addDays(15)->format('Y-m-d');
                    break;
                case '1_month':
                    $deliveryDate = now()->addMonth()->format('Y-m-d');
                    break;
                case '3_months':
                    $deliveryDate = now()->addMonths(3)->format('Y-m-d');
                    break;
                default:
                    // Try to parse as a real date (Y-m-d format)
                    try {
                        $parsedDate = \Carbon\Carbon::parse($request->delivery_date);
                        $deliveryDate = $parsedDate->format('Y-m-d');

                        // Check if delivery date is in the past
                        if ($parsedDate->isPast()) {
                            \Log::warning('Delivery date is in the past: ' . $deliveryDate);
                            return $query->whereRaw('1 = 0'); // Return empty result
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Could not parse delivery date: ' . $request->delivery_date);
                        return $query;
                    }
                    break;
            }
        }

        // If both days and delivery date are provided, filter by availability
        if ($days && $deliveryDate)
        {
            $startDate = now()->format('Y-m-d');
            $endDate = $deliveryDate;

            // Debug: Log the parameters
            \Log::info('Availability Filter Debug', [
                'days' => $days,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'deliveryDate' => $deliveryDate,
                'originalDeliveryDate' => $request->delivery_date,
            ]);

            // Apply a more precise filter using a subquery
            $availableCollaboratorIds = $this->getAvailableCollaboratorIds($startDate, $endDate, $days);

            // Debug: Log available IDs (only if very few results)
            if (count($availableCollaboratorIds) < 10)
            {
                \Log::info('Available Collaborator IDs', [
                    'count' => count($availableCollaboratorIds),
                    'ids' => $availableCollaboratorIds,
                    'requiredDays' => $days,
                    'period' => $startDate . ' to ' . $endDate,
                ]);
            }

            if (!empty($availableCollaboratorIds))
            {
                $query->whereIn('id', $availableCollaboratorIds);
            }
            else
            {
                // If no collaborators are available, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    /**
     * Apply dashboard filter based on the selected filter type
     */
    private function applyDashboardFilter(QueryBuilder $query, $filterType)
    {
        switch ($filterType)
        {
            case 'pending-acceptance':
                // Contacts without a linked user
                $query->whereNull('user_id');
                break;

            case 'collaborators':
                // Contacts with a user that has the 'collaborator' role
                $query->whereHas('user', function ($q)
                {
                    $q->whereHas('roles', function ($roleQuery)
                    {
                        $roleQuery->where('name', 'collaborator');
                    });
                });
                break;

            case 'new-this-week':
                // Contacts created in the last week and linked to a user
                $query->whereNotNull('user_id')
                    ->where('created_at', '>=', now()->subWeek());
                break;

            case 'not-updated-six-months':
                // Contacts not updated in the last 6 months
                $query->where('updated_at', '<=', now()->subMonths(6));
                break;

            default:
                // No filter applied
                break;
        }

        return $query;
    }

    /**
     * Get collaborator IDs that have enough available days in the given period
     */
    private function getAvailableCollaboratorIds($startDate, $endDate, $requiredDays)
    {
        $availableIds = [];

        // Get all collaborators with their weekly availability and absences
        // Note: Global scope 'team' is already applied automatically
        $collaborators = Contact::with(['weeklyAvailability', 'absences' => function ($q) use ($startDate, $endDate)
        {
            $q->whereBetween('absence_date', [$startDate, $endDate]);
        }])
            // Temporarily commented for debugging - only get collaborators that are linked to users with collaborator role
            // ->whereHas('user', function ($q) {
            //     $q->whereHas('roles', function ($roleQuery) {
            //         $roleQuery->where('name', 'collaborator');
            //     });
            // })
            ->get();

        foreach ($collaborators as $collaborator)
        {
            $availableDays = $this->calculateAvailableDays($collaborator, $startDate, $endDate);

            if ($availableDays >= $requiredDays)
            {
                $availableIds[] = $collaborator->id;
            }
        }

        return $availableIds;
    }

    /**
     * Calculate available days for a collaborator in a given period
     */
    private function calculateAvailableDays($collaborator, $startDate, $endDate)
    {
        $weeklyAvailability = $collaborator->weeklyAvailability;

        // If no weekly availability is set, assume all days are available (many work weekends)
        if (!$weeklyAvailability)
        {
            $weeklyPattern = [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => true,
                'sunday' => true,
            ];
        }
        else
        {
            $weeklyPattern = [
                'monday' => $weeklyAvailability->monday,
                'tuesday' => $weeklyAvailability->tuesday,
                'wednesday' => $weeklyAvailability->wednesday,
                'thursday' => $weeklyAvailability->thursday,
                'friday' => $weeklyAvailability->friday,
                'saturday' => $weeklyAvailability->saturday,
                'sunday' => $weeklyAvailability->sunday,
            ];
        }

        // Get specific absence dates
        $absenceDates = $collaborator->absences->pluck('absence_date')->map(function ($date)
        {
            return $date->format('Y-m-d');
        })->toArray();

        $availableDays = 0;
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        while ($currentDate->lte($endDateCarbon))
        {
            $dayOfWeek = strtolower($currentDate->format('l'));
            $dateString = $currentDate->format('Y-m-d');

            // Check if this day is available according to weekly pattern
            $isWeeklyAvailable = $weeklyPattern[$dayOfWeek] ?? false;

            // Check if this specific date is not in absences
            $isNotAbsent = !in_array($dateString, $absenceDates);

            // Day is available if both conditions are met
            if ($isWeeklyAvailable && $isNotAbsent)
            {
                $availableDays++;
            }

            $currentDate->addDay();
        }

        // Debug for first few collaborators (only when there are issues)
        if ($collaborator->id <= 3 && $availableDays < 5)
        {
            \Log::info("Collaborator {$collaborator->id} availability calculation", [
                'name' => $collaborator->name,
                'hasWeeklyAvailability' => $weeklyAvailability ? 'yes' : 'no',
                'weeklyPattern' => $weeklyPattern,
                'absencesCount' => count($absenceDates),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'calculatedDays' => $availableDays,
                'totalPeriodDays' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1,
            ]);
        }

        return $availableDays;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('collaborator-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Brtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->buttons([
                [
                    'extend' => 'csv',
                    'text' => '<i class="ti ti-file-text me-1"></i> CSV',
                    'className' => 'btn btn-outline-info',
                    'title' => 'Collaborators',
                    'filename' => 'collaborators_' . date('Y-m-d_H-i-s'),
                    'exportOptions' => [
                        'columns' => [1, 2, 3, 4, 5], // Export only important visible columns
                    ],
                ],
                [
                    'extend' => 'pdf',
                    'text' => '<i class="ti ti-file-text me-1"></i> PDF',
                    'className' => 'btn btn-outline-danger',
                    'title' => 'Collaborators',
                    'filename' => 'collaborators_' . date('Y-m-d_H-i-s'),
                    'exportOptions' => [
                        'columns' => [1, 2, 3, 4, 5], // Export only important visible columns
                    ],
                    'orientation' => 'landscape',
                    'pageSize' => 'A4',
                ],
            ])
            ->parameters([
                'initComplete' => "function() {
					var api = this.api();
					api.columns('.select-filter').every(function() {
						var column = this;
						$('#CategoryFilter').on('change', function() {
							let selectedValue = $(this).val();
							api.column(3).search(selectedValue ? selectedValue : '', true, false).draw();
						});
					});
				}",
                'drawCallback' => "function() {
					$('#CategoryFilter').off('change').on('change', function() {
						let selectedValue = $(this).val();
						$('#collaborator-table').DataTable().column(3).search(selectedValue ? selectedValue : '', true, false).draw();
					});
				}",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Collaborator'))
                ->addClass('all'),
            Column::make('rating')
                ->title(__('Rating'))
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(false)
                ->orderable(false)
                ->width(120),
            Column::make('language_combinations')
                ->title(__('Combination'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false)
                ->width(180),
            Column::make('services')
                ->title(__('Services'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(true),
            Column::make('projects')
                ->title(__('Projects'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->width(100),
            Column::computed('action')
                ->title(__('Acciones'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'Collaborator_' . date('YmdHis');
    }
}
