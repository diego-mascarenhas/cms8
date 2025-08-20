<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		$user = auth()->user();

		// Check if user can view any invoices
		if (! $user->can('viewAny', Invoice::class))
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized to view invoices',
				'data' => [],
			], 403);
		}

		// Apply role-based filtering
		$query = Invoice::query();
		$filter = InvoicePolicy::getQueryFilter($user);
		$filter($query);

		$invoices = $query->with(['enterprise:id,name', 'type:id,name'])
			->orderBy('created_at', 'desc')
			->paginate(20);

		return response()->json([
			'success' => true,
			'message' => 'Invoices retrieved successfully',
			'data' => $invoices->items(),
			'pagination' => [
				'current_page' => $invoices->currentPage(),
				'per_page' => $invoices->perPage(),
				'total' => $invoices->total(),
				'last_page' => $invoices->lastPage(),
			],
			'user_info' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'role' => $user->roles->first()->name ?? 'No role',
			],
		]);
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		$user = auth()->user();

		if (! $user->can('create', Invoice::class))
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized to create invoices',
			], 403);
		}

		// Validate the request
		$validatedData = $request->validate([
			'enterprise_id' => 'required|exists:enterprises,id',
			'billing_id' => 'nullable|integer',
			'type_id' => 'required|exists:invoice_types,id',
			'operation' => 'nullable|string|max:255',
			'number' => 'required|string|max:255',
			'date' => 'required|date',
			'due_date' => 'nullable|date|after_or_equal:date',
			'gross_amount' => 'required|numeric|min:0',
			'discount' => 'nullable|numeric|min:0',
			'total_amount' => 'required|numeric|min:0',
			'balance' => 'nullable|numeric',
			'status' => 'required|integer|min:1|max:8',
		]);

		try
		{
			$invoice = Invoice::create($validatedData);

			return response()->json([
				'success' => true,
				'message' => 'Invoice created successfully',
				'data' => $invoice->load(['enterprise:id,name', 'type:id,name']),
				'user_info' => [
					'id' => $user->id,
					'name' => $user->name,
					'email' => $user->email,
					'role' => $user->roles->first()->name ?? 'No role',
				],
			], 201);
		} catch (\Exception $e)
		{
			return response()->json([
				'success' => false,
				'message' => 'Error creating invoice: '.$e->getMessage(),
			], 500);
		}
	}

	/**
	 * Display the specified resource.
	 */
	public function show(string $id)
	{
		$user = auth()->user();
		$invoice = Invoice::with(['enterprise:id,name', 'type:id,name'])->find($id);

		if (! $invoice)
		{
			return response()->json([
				'success' => false,
				'message' => 'Invoice not found',
			], 404);
		}

		if (! $user->can('view', $invoice))
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized to view this invoice',
			], 403);
		}

		return response()->json([
			'success' => true,
			'message' => 'Invoice retrieved successfully',
			'data' => $invoice,
			'user_info' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'role' => $user->roles->first()->name ?? 'No role',
			],
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, string $id)
	{
		$user = auth()->user();
		$invoice = Invoice::find($id);

		if (! $invoice)
		{
			return response()->json([
				'success' => false,
				'message' => 'Invoice not found',
			], 404);
		}

		if (! $user->can('update', $invoice))
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized to update this invoice',
			], 403);
		}

		// Validate the request
		$validatedData = $request->validate([
			'enterprise_id' => 'sometimes|required|exists:enterprises,id',
			'billing_id' => 'nullable|integer',
			'type_id' => 'sometimes|required|exists:invoice_types,id',
			'operation' => 'nullable|string|max:255',
			'number' => 'sometimes|required|string|max:255',
			'date' => 'sometimes|required|date',
			'due_date' => 'nullable|date|after_or_equal:date',
			'gross_amount' => 'sometimes|required|numeric|min:0',
			'discount' => 'nullable|numeric|min:0',
			'total_amount' => 'sometimes|required|numeric|min:0',
			'balance' => 'nullable|numeric',
			'status' => 'sometimes|required|integer|min:1|max:8',
		]);

		try
		{
			$invoice->update($validatedData);

			return response()->json([
				'success' => true,
				'message' => 'Invoice updated successfully',
				'data' => $invoice->load(['enterprise:id,name', 'type:id,name']),
				'user_info' => [
					'id' => $user->id,
					'name' => $user->name,
					'email' => $user->email,
					'role' => $user->roles->first()->name ?? 'No role',
				],
			]);
		} catch (\Exception $e)
		{
			return response()->json([
				'success' => false,
				'message' => 'Error updating invoice: '.$e->getMessage(),
			], 500);
		}
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(string $id)
	{
		$user = auth()->user();
		$invoice = Invoice::find($id);

		if (! $invoice)
		{
			return response()->json([
				'success' => false,
				'message' => 'Invoice not found',
			], 404);
		}

		if (! $user->can('delete', $invoice))
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized to delete this invoice',
			], 403);
		}

		try
		{
			$invoice->delete();

			return response()->json([
				'success' => true,
				'message' => 'Invoice deleted successfully',
				'user_info' => [
					'id' => $user->id,
					'name' => $user->name,
					'email' => $user->email,
					'role' => $user->roles->first()->name ?? 'No role',
				],
			]);
		} catch (\Exception $e)
		{
			return response()->json([
				'success' => false,
				'message' => 'Error deleting invoice: '.$e->getMessage(),
			], 500);
		}
	}
}
