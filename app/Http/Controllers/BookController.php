<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('Books/Index', [
            'books' => Book::query()
                ->with(['category:id,name', 'authors:id,display_name', 'copies:id,book_id,inventory_number,status'])
                ->withCount('copies')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhereHas('copies', fn ($query) => $query->where('inventory_number', 'like', "%{$search}%"))
                        ->orWhereHas('authors', fn ($query) => $query->where('display_name', 'like', "%{$search}%"));
                }))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Books/Create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $book = DB::transaction(fn (): Book => $this->persist(new Book, $data));

        return to_route('books.index')->with('success', "Ouvrage « {$book->title} » créé avec succès.");
    }

    public function edit(Book $book): Response
    {
        return Inertia::render('Books/Edit', ['book' => $book->load('authors:id,display_name'), 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        DB::transaction(fn (): Book => $this->persist($book, $this->validatedData($request)));

        return to_route('books.index')->with('success', "Ouvrage « {$book->title} » mis à jour.");
    }

    public function destroy(Book $book): RedirectResponse
    {
        if ($book->copies()->exists()) {
            return back()->withErrors(['book' => 'Cet ouvrage possède des exemplaires. Supprimez ou archivez d’abord ses exemplaires.']);
        }
        $title = $book->title;
        $book->delete();

        return to_route('books.index')->with('success', "Ouvrage « {$title} » supprimé.");
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['required', 'array', 'min:1'],
            'authors.*' => ['required', 'string', 'max:255', 'distinct'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:50'],
            'edition' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function persist(Book $book, array $data): Book
    {
        $book->fill(collect($data)->except('authors')->all())->save();
        $authors = collect($data['authors'])->mapWithKeys(function (string $name, int $position) {
            $author = Author::query()->create(['display_name' => trim($name)]);

            return [$author->id => ['position' => $position + 1]];
        });
        $book->authors()->sync($authors);

        return $book;
    }
}
