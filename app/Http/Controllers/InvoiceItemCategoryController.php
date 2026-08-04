<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceItemCategoryRequest;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;

class InvoiceItemCategoryController extends Controller
{
    public function update(UpdateInvoiceItemCategoryRequest $request, InvoiceItem $invoiceItem): JsonResponse
    {
        $invoiceItem->loadMissing(['invoice', 'category']);

        abort_if($invoiceItem->invoice === null, 404);

        $this->authorize('update', $invoiceItem->invoice);

        $teamId = (int) auth()->user()->currentTeam->id;
        abort_if((int) $invoiceItem->invoice->team_id !== $teamId, 403);

        $categoryId = $request->input('category_id');
        $categoryId = $categoryId === null || $categoryId === '' ? null : (int) $categoryId;

        $invoiceItem->forceFill([
            'category_id' => $categoryId,
        ])->save();

        $invoiceItem->load('category');

        return response()->json([
            'success' => true,
            'category_id' => $invoiceItem->category_id ? (int) $invoiceItem->category_id : null,
            'category_name' => (string) ($invoiceItem->category?->name ?? __('Uncategorized')),
        ]);
    }
}
