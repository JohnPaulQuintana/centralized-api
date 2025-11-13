<?php

namespace App\Http\Controllers\SmartHouse;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        $userId = auth()->id(); // get authenticated user ID
        $expenses = Expense::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->paginate(10);

        return response()->json($expenses);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'amount' => 'required|numeric',
            'date' => 'required|date',
        ]);

        // Attach the authenticated user's ID
        $validated['user_id'] = $request->user()->id;

        $expense = Expense::create($validated);

        return response()->json($expense, 201);
    }


    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'amount' => 'required|numeric',
            'date' => 'required|date',
        ]);

        $expense->update($validated);
        return response()->json($expense);
    }

    public function destroy($id)
    {
        Expense::destroy($id);
        return response()->json(['message' => 'Deleted successfully']);
    }

    // New: Overview method
    public function overview(Request $request)
    {
        $userId = auth()->id();
        $budget = $request->input('budget', 50000); // default if not sent

        // Get all expenses for the user
        $expenses = Expense::where('user_id', $userId)->get();

        // Total spent across all expenses
        $totalSpent = $expenses->sum('amount');

        // Expenses for today
        $today = date('Y-m-d');
        $todayExpenses = $expenses->filter(fn($e) => $e->date == $today);
        $todaySpent = $todayExpenses->sum('amount');

        // Daily average across all days with expenses
        $dailyTotals = $expenses
            ->groupBy('date')       // group by date
            ->map(fn($group) => $group->sum('amount')); // total per day
        $dailyAvg = $dailyTotals->count() ? round($dailyTotals->avg(), 2) : 0;

        // Top category overall
        $topCategory = $expenses->groupBy('category')
            ->map(fn($group) => $group->sum('amount'))
            ->sortDesc()
            ->keys()
            ->first() ?? '';

        // Budget left
        $budgetLeft = $budget - $totalSpent;

        return response()->json([
            'totalSpent' => $totalSpent,
            'todaySpent' => $todaySpent,
            'dailyAvg' => $dailyAvg,
            'topCategory' => $topCategory,
            'budgetLeft' => $budgetLeft,
        ]);
    }

    public function weeklySpending(Request $request)
    {
        $userId = auth()->id();

        // Get last 7 days
        $dates = collect(range(0, 6))->map(fn($i) => now()->subDays($i)->format('Y-m-d'))->reverse();

        $expenses = Expense::where('user_id', $userId)
            ->whereIn('date', $dates)
            ->get()
            ->groupBy('date')
            ->map(fn($group) => $group->sum('amount'));

        // Fill missing days with 0
        $spending = $dates->map(fn($date) => $expenses[$date] ?? 0);

        return response()->json([
            'labels' => $dates->map(fn($d) => date('D', strtotime($d))),
            'data' => $spending,
        ]);
    }
}
