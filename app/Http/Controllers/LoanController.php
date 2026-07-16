<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,overdue,closed'],
        ]);
        $ownOnly = $request->user()->hasRole('etudiant');
        $studentId = $ownOnly ? $request->user()->student?->id : null;
        $scope = fn (): Builder => Loan::query()->when($ownOnly, fn (Builder $query) => $query->where('student_id', $studentId ?? 0));
        $search = trim((string) ($filters['search'] ?? ''));

        $loans = $scope()->with([
            'student:id,registration_number,academic_number,last_name,first_name,photo_path',
            'items.copy.book',
        ])->withCount([
            'items',
            'items as active_items_count' => fn (Builder $query) => $query->whereNull('returned_at'),
        ])->when($search, fn (Builder $query) => $query->where(function (Builder $nested) use ($search) {
            $nested->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $student) => $student
                    ->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('academic_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%"))
                ->orWhereHas('items.copy', fn (Builder $copy) => $copy->where('inventory_number', 'like', "%{$search}%"));
        }))->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('closed_at')->where('due_at', '>=', now()))
            ->when(($filters['status'] ?? null) === 'overdue', fn (Builder $query) => $query->whereNull('closed_at')->where('due_at', '<', now()))
            ->when(($filters['status'] ?? null) === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
            ->orderByRaw('closed_at is null desc')
            ->orderBy('due_at')
            ->paginate(15)->withQueryString();

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? ''],
            'stats' => [
                'active' => $scope()->whereNull('closed_at')->where('due_at', '>=', now())->count(),
                'overdue' => $scope()->whereNull('closed_at')->where('due_at', '<', now())->count(),
                'closed' => $scope()->whereNotNull('closed_at')->count(),
                'booksOut' => $scope()->whereNull('closed_at')->withCount(['items as active_count' => fn (Builder $query) => $query->whereNull('returned_at')])->get()->sum('active_count'),
            ],
            'ownOnly' => $ownOnly,
        ]);
    }
}
