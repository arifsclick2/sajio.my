<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Expenses (§19) — money-out records with category/description/amount/date/
 * payment method/note/created-by. Owner/manager only. Soft-deleted so the
 * financial history is never hard-destroyed (§26).
 *
 * Summary stays simple: `Sales - Expenses = Net Position`.
 */
class ExpenseController extends Controller
{
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    private function ownedExpense(Restaurant $restaurant, Expense $expense): Expense
    {
        if ($expense->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your expense.');
        }

        return $expense;
    }

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = Expense::query()
            ->forRestaurant($restaurant->id)
            ->when(! empty($validated['from']), fn ($q) => $q->whereDate('expense_date', '>=', $validated['from']))
            ->when(! empty($validated['to']), fn ($q) => $q->whereDate('expense_date', '<=', $validated['to']))
            ->when(! empty($validated['category_id']), fn ($q) => $q->where('category_id', $validated['category_id']))
            ->when(! empty($validated['q']), fn ($q) => $q->where('description', 'like', '%'.$validated['q'].'%'))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $rows = $base->with(['category:id,name', 'createdBy:id,name'])->get();
        $total = (float) $rows->sum('amount');

        $byCategory = $rows->groupBy(fn (Expense $e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn ($group, string $name) => [
                'category' => $name,
                'count' => $group->count(),
                'amount' => number_format((float) $group->sum('amount'), 2, '.', ''),
            ])
            ->sortByDesc(fn ($row) => $row['amount'])
            ->values();

        // Paginate a clone for the list.
        $paginated = (clone $base)->paginate($validated['per_page'] ?? 50)
            ->withQueryString()
            ->through(fn (Expense $e) => $this->shape($e));

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'count' => $rows->count(),
                'total_amount' => number_format($total, 2, '.', ''),
                'by_category' => $byCategory->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $validated = $this->validatePayload($request);

        $expense = Expense::query()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $validated['category_id'] ?? null,
            'description' => trim($validated['description']),
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['expense' => $this->shape($expense->fresh(['category:id,name', 'createdBy:id,name']))], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->ownedExpense($restaurant, $expense);
        $validated = $this->validatePayload($request, partial: true);

        $expense->update($validated);

        return response()->json(['expense' => $this->shape($expense->fresh(['category:id,name', 'createdBy:id,name']))]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->ownedExpense($restaurant, $expense);

        // Soft delete — keeps the audit trail intact (§26).
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'description' => [$sometimes, 'string', 'max:255'],
            'amount' => [$sometimes, 'numeric', 'min:0.01', 'max:10000000'],
            'expense_date' => [$sometimes, 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'payment_method' => ['nullable', Rule::in(Expense::METHODS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function shape(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'category_id' => $expense->category_id,
            'category' => $expense->category ? ['id' => $expense->category->id, 'name' => $expense->category->name] : null,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'expense_date' => $expense->expense_date?->format('Y-m-d'),
            'payment_method' => $expense->payment_method,
            'note' => $expense->note,
            'created_by' => $expense->createdBy ? ['id' => $expense->createdBy->id, 'name' => $expense->createdBy->name] : null,
            'created_at' => $expense->created_at?->toISOString(),
        ];
    }
}
