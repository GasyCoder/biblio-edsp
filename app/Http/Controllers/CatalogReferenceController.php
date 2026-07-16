<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Location;
use App\Services\CategoryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogReferenceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CatalogReferences/Index', [
            'counts' => [
                'categories' => Category::query()->count(),
                'authors' => Author::query()->count(),
                'locations' => Location::query()->count(),
            ],
        ]);
    }

    public function categories(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('CatalogReferences/Categories', [
            'categories' => Category::query()->withCount('books')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('inventory_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }))->orderBy('name')->get(),
            'filters' => ['search' => $search],
        ]);
    }

    public function authors(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('CatalogReferences/Authors', [
            'authors' => Author::query()->withCount('books')
                ->when($search !== '', fn ($query) => $query->where('display_name', 'like', "%{$search}%"))
                ->orderBy('display_name')->paginate(25)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function locations(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('CatalogReferences/Locations', [
            'locations' => Location::query()->withCount('copies')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }))->orderBy('code')->get(),
            'filters' => ['search' => $search],
        ]);
    }

    public function storeCategory(Request $request, CategoryCodeService $codes): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'inventory_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', 'unique:categories,inventory_code'], 'description' => ['nullable', 'string', 'max:2000']]);
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        for ($suffix = 2; Category::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $baseSlug.'-'.$suffix;
        }
        Category::query()->create([...$data, 'inventory_code' => Str::upper(($data['inventory_code'] ?? null) ?: $codes->generate($data['name'])), 'slug' => $slug]);

        return back()->with('success', 'Catégorie créée avec succès.');
    }

    public function storeAuthor(Request $request): RedirectResponse
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:255']]);
        Author::query()->create($data);

        return back()->with('success', 'Auteur créé avec succès.');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $this->locationData($request);
        Location::query()->create($data);

        return back()->with('success', 'Emplacement créé avec succès.');
    }

    public function updateCategory(Request $request, Category $category, CategoryCodeService $codes): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'inventory_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('categories', 'inventory_code')->ignore($category)], 'description' => ['nullable', 'string', 'max:2000']]);
        $slug = Str::slug($data['name']);
        if (Category::withTrashed()->where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            $slug .= '-'.$category->id;
        }
        $category->update([...$data, 'inventory_code' => Str::upper(($data['inventory_code'] ?? null) ?: $codes->generate($data['name'], $category->id)), 'slug' => $slug]);

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->books()->exists()) {
            return back()->withErrors(['category' => 'Cette catégorie est utilisée par des ouvrages.']);
        }
        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    public function destroyCategoriesBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:categories,id']]);
        $categories = Category::query()->whereKey($data['ids'])->withCount(['books', 'children'])->get();
        $protected = $categories->first(fn (Category $category) => $category->books_count > 0 || $category->children_count > 0);

        if ($protected) {
            return back()->withErrors(['category' => "La catégorie « {$protected->name} » est utilisée par des ouvrages ou des sous-catégories. La suppression groupée a été annulée."]);
        }

        DB::transaction(fn () => $categories->each->delete());

        return back()->with('success', $categories->count().' catégorie(s) supprimée(s).');
    }

    public function updateAuthor(Request $request, Author $author): RedirectResponse
    {
        $author->update($request->validate(['display_name' => ['required', 'string', 'max:255']]));

        return back()->with('success', 'Auteur mis à jour.');
    }

    public function destroyAuthor(Author $author): RedirectResponse
    {
        if ($author->books()->exists()) {
            return back()->withErrors(['author' => 'Cet auteur est associé à des ouvrages.']);
        }
        $author->delete();

        return back()->with('success', 'Auteur supprimé.');
    }

    public function destroyAuthorsBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:authors,id']]);
        $authors = Author::query()->whereKey($data['ids'])->withCount('books')->get();
        $protected = $authors->firstWhere('books_count', '>', 0);

        if ($protected) {
            return back()->withErrors(['author' => "L’auteur « {$protected->display_name} » est associé à des ouvrages. La suppression groupée a été annulée."]);
        }

        DB::transaction(fn () => $authors->each->delete());

        return back()->with('success', $authors->count().' auteur(s) supprimé(s).');
    }

    public function updateLocation(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->locationData($request, $location));

        return back()->with('success', 'Emplacement mis à jour.');
    }

    public function destroyLocation(Location $location): RedirectResponse
    {
        if ($location->copies()->exists()) {
            return back()->withErrors(['location' => 'Cet emplacement contient encore des exemplaires.']);
        }
        $location->delete();

        return back()->with('success', 'Emplacement supprimé.');
    }

    /** @return array<string, mixed> */
    private function locationData(Request $request, ?Location $location = null): array
    {
        $data = $request->validate(['type' => ['required', Rule::in(['cabinet', 'shelf', 'other'])], 'number' => ['required', 'string', 'max:50'], 'name' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $prefix = match ($data['type']) {
            'cabinet' => 'ARM', 'shelf' => 'ETA', default => 'AUT'
        };
        $label = match ($data['type']) {
            'cabinet' => 'Armoire', 'shelf' => 'Étagère', default => trim((string) ($data['name'] ?? 'Autre')) ?: 'Autre'
        };
        $code = $prefix.'-'.Str::upper(Str::slug($data['number']));
        if (Location::withTrashed()->where('code', $code)->when($location, fn ($query) => $query->whereKeyNot($location->id))->exists()) {
            throw ValidationException::withMessages(['number' => 'Cet emplacement existe déjà.']);
        }

        return [...$data, 'code' => $code, 'name' => $data['type'] === 'other' ? $label.' '.$data['number'] : $label.' '.$data['number']];
    }
}
