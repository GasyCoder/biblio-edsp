<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $category = $request->integer('category') ?: null;
        $availability = $request->string('availability')->toString();
        $year = $request->integer('year') ?: null;

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
                ->when($category, fn ($query) => $query->where('category_id', $category))
                ->when($year, fn ($query) => $query->where('publication_year', $year))
                ->when($availability === 'no_copies', fn ($query) => $query->doesntHave('copies'))
                ->when(in_array($availability, ['available', 'in_consultation', 'borrowed'], true), fn ($query) => $query->whereHas('copies', fn ($query) => $query->where('status', $availability)))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search, 'category' => $category, 'availability' => $availability, 'year' => $year],
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'years' => Book::query()->whereNotNull('publication_year')->distinct()->orderByDesc('publication_year')->pluck('publication_year'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Books/Create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'aiAvailable' => filled(config('services.cloudflare.account_id')) && filled(config('services.cloudflare.api_token')),
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
        return Inertia::render('Books/Edit', ['book' => $book->load('authors:id,display_name'), 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'aiAvailable' => filled(config('services.cloudflare.account_id')) && filled(config('services.cloudflare.api_token'))]);
    }

    public function show(Book $book): Response
    {
        return Inertia::render('Books/Show', [
            'book' => $book->load(['category:id,name', 'authors:id,display_name', 'copies' => fn ($query) => $query->with('location:id,code,name')->orderBy('inventory_number')]),
        ]);
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

    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:books,id']]);
        $books = Book::query()->whereKey($data['ids'])->withCount('copies')->get();
        $protected = $books->firstWhere('copies_count', '>', 0);

        if ($protected) {
            return back()->withErrors(['book' => "L’ouvrage « {$protected->title} » possède encore des exemplaires. La suppression groupée a été annulée."]);
        }

        DB::transaction(fn () => $books->each->delete());

        return to_route('books.index')->with('success', $books->count().' ouvrage(s) supprimé(s).');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
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
        $oldCover = $book->cover_path;
        $coverPath = $oldCover;
        if (! empty($data['cover'])) {
            $coverPath = $data['cover']->store('covers/books', 'public');
        } elseif (! empty($data['remove_cover'])) {
            $coverPath = null;
        }
        $book->fill([...collect($data)->except(['authors', 'cover', 'remove_cover'])->all(), 'cover_path' => $coverPath])->save();
        if ($oldCover && $oldCover !== $coverPath) {
            Storage::disk('public')->delete($oldCover);
        }
        $authors = collect($data['authors'])->mapWithKeys(function (string $name, int $position) {
            $author = Author::query()->create(['display_name' => trim($name)]);

            return [$author->id => ['position' => $position + 1]];
        });
        $book->authors()->sync($authors);

        return $book;
    }
}
