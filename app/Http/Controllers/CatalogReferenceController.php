<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'unique:locations,code'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        Location::query()->create($data);

        return back()->with('success', 'Emplacement créé avec succès.');
    }
}
