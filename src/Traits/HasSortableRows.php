<?php

namespace OptimistDigital\NovaSortable\Traits;

use Laravel\Nova\Http\Requests\NovaRequest;

trait HasSortableRows
{
    public static function getSortability(NovaRequest $request)
    {
        $model = null;

        $resource = isset($request->resourceId) ? $request->findResourceOrFail() : $request->newResource();
        $model = $resource->resource ?? null;

        $sortable = $model->sortable ?? false;
        $sortOnBelongsTo = $resource->disableSortOnIndex ?? false;

        if ($request->viaManyToMany()) {
            $relationshipQuery = $request->findParentModel()->{$request->viaRelationship}();

            if (isset($request->resourceId)) {
                $tempModel = $relationshipQuery->first()->pivot ?? null;
                $model = !empty($tempModel) ?
                    $relationshipQuery->withPivot($tempModel->getKeyName(), $tempModel->sortable['order_column_name'])->find($request->resourceId)->pivot
                    : null;
            } else {
                $model = $relationshipQuery->first()->pivot ?? null;
            }

            $sortable = $model->sortable ?? false;
            $sortOnBelongsTo = !empty($sortable);
        }

        $sortOnHasMany = $sortable['sort_on_has_many'] ?? false;

        return (object) [
            'model' => $model,
            'sortable' => $sortable,
            'sortOnBelongsTo' => $sortOnBelongsTo,
            'sortOnHasMany' => $sortOnHasMany,
        ];
    }

    public function serializeForIndex(NovaRequest $request, $fields = null)
    {
        $sortability = static::getSortability($request);

        return array_merge(parent::serializeForIndex($request, $fields), [
            'sortable' => $sortability->sortable,
            'sort_on_index' => !$sortability->sortOnHasMany && !$sortability->sortOnBelongsTo,
            'sort_on_has_many' => $sortability->sortOnHasMany,
            'sort_on_belongs_to' => $sortability->sortOnBelongsTo,
        ]);
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $sortability = static::getSortability($request);

        $shouldSort = true;
        if (empty($sortability->sortable)) $shouldSort = false;
        if ($sortability->sortOnBelongsTo && empty($request->viaResource())) $shouldSort = false;
        if ($sortability->sortOnHasMany && empty($request->viaResource())) $shouldSort = false;

        if (empty($request->get('orderBy')) && $shouldSort) {
            $query->getQuery()->orders = [];
            $orderColumn = !empty($sortability->sortable['order_column_name']) ? $sortability->sortable['order_column_name'] : 'sort_order';
            return $query->orderBy($orderColumn);
        }

        return $query;
    }
}
