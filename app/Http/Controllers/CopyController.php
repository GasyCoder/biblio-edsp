<?php

namespace App\Http\Controllers;

use App\Enums\BarcodeSymbology;
use App\Enums\CopyCondition;
use App\Models\Book;
use App\Models\Copy;
use App\Models\Location;
use App\Services\BarcodeService;
use App\Services\CopyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CopyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('Copies/Index', ['copies' => Copy::query()->with(['book:id,title', 'location:id,code,name'])->when($search, fn ($query) => $query->where('inventory_number', 'like', "%{$search}%")->orWhereHas('book', fn ($query) => $query->where('title', 'like', "%{$search}%")))->latest()->paginate(20)->withQueryString(), 'filters' => ['search' => $search]]);
    }

    public function create(): Response
    {
        return Inertia::render('Copies/Create', ['books' => Book::query()->orderBy('title')->get(['id', 'title']), 'locations' => Location::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']), 'conditions' => collect(CopyCondition::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])]);
    }

    public function store(Request $request, CopyService $copies): RedirectResponse
    {
        $data = $request->validate(['book_id' => ['required', 'exists:books,id'], 'location_id' => ['nullable', 'exists:locations,id'], 'condition' => ['required', Rule::enum(CopyCondition::class)], 'barcode_symbology' => ['required', Rule::enum(BarcodeSymbology::class)], 'notes' => ['nullable', 'string', 'max:2000']]);
        $copy = $copies->create($data);

        return to_route('copies.index')->with('success', "Exemplaire {$copy->inventory_number} enregistré.");
    }

    public function print(Copy $copy, BarcodeService $barcodes): View
    {
        $copy->load('book');

        return view('print.copy-label', ['copy' => $copy, 'codeSvg' => $copy->barcode_symbology === BarcodeSymbology::Qr ? $barcodes->qrSvg($copy->barcode_value) : $barcodes->code128Svg($copy->barcode_value)]);
    }
}
