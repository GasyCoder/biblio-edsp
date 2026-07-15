<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'categories' => Category::query()->withCount('books')->orderBy('name')->get(),
            'authors' => Author::query()->withCount('books')->orderBy('display_name')->paginate(20, ['*'], 'authors_page'),
            'locations' => Location::query()->withCount('copies')->orderBy('code')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        for ($suffix = 2; Category::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $baseSlug.'-'.$suffix;
        }
        Category::query()->create([...$data, 'slug' => $slug]);

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

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $slug = Str::slug($data['name']);
        if (Category::withTrashed()->where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            $slug .= '-'.$category->id;
        }
        $category->update([...$data, 'slug' => $slug]);

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
