<?php

namespace App\Http\Controllers;

use App\Enums\BarcodeSymbology;
use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use App\Models\Book;
use App\Models\Copy;
use App\Models\Location;
use App\Services\BarcodeService;
use App\Services\CopyService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CopyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('Copies/Index', [
            'copies' => Copy::query()
                ->with([
                    'book:id,title',
                    'location:id,code,type,number,name',
                    'activeConsultationItems.session.student:id,registration_number,first_name,last_name',
                    'activeLoanItems.loan.student:id,registration_number,first_name,last_name',
                ])
                ->when($search, fn ($query) => $query->where('inventory_number', 'like', "%{$search}%")
                    ->orWhereHas('book', fn ($query) => $query->where('title', 'like', "%{$search}%")))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['search' => $search],
            'conditionLabels' => collect(CopyCondition::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]),
            'statusLabels' => collect(CopyStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]),
        ]);
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

    public function edit(Copy $copy): Response
    {
        return Inertia::render('Copies/Edit', ['copy' => $copy->load('book:id,title'), 'locations' => Location::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'type', 'number', 'name']), 'conditions' => collect(CopyCondition::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]), 'statuses' => collect([CopyStatus::Available, CopyStatus::Damaged, CopyStatus::Lost, CopyStatus::Archived])->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])]);
    }

    public function update(Request $request, Copy $copy): RedirectResponse
    {
        if (in_array($copy->status, [CopyStatus::InConsultation, CopyStatus::Borrowed], true)) {
            return back()->withErrors(['status' => 'Un exemplaire engagé dans une opération ne peut pas être modifié.']);
        }
        $data = $request->validate(['location_id' => ['nullable', 'exists:locations,id'], 'condition' => ['required', Rule::enum(CopyCondition::class)], 'status' => ['required', Rule::in([CopyStatus::Available->value, CopyStatus::Damaged->value, CopyStatus::Lost->value, CopyStatus::Archived->value])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $copy->update([...$data, 'lock_version' => $copy->lock_version + 1]);

        return to_route('copies.index')->with('success', "Exemplaire {$copy->inventory_number} mis à jour.");
    }

    public function destroy(Copy $copy): RedirectResponse
    {
        if (in_array($copy->status, [CopyStatus::InConsultation, CopyStatus::Borrowed], true) || $copy->consultationItems()->exists()) {
            return back()->withErrors(['copy' => 'Cet exemplaire possède un historique ou une opération active et ne peut pas être supprimé. Archivez-le plutôt.']);
        }
        $number = $copy->inventory_number;
        $copy->delete();

        return to_route('copies.index')->with('success', "Exemplaire {$number} supprimé.");
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:copies,id']]);
        $copies = Copy::query()->whereKey($data['ids'])->withCount('consultationItems')->get();

        $protected = $copies->first(fn (Copy $copy) => in_array($copy->status, [CopyStatus::InConsultation, CopyStatus::Borrowed], true) || $copy->consultation_items_count > 0);
        if ($protected) {
            return back()->withErrors(['copy' => "L’exemplaire {$protected->inventory_number} possède un historique ou une opération active. La suppression groupée a été annulée."]);
        }

        DB::transaction(fn () => $copies->each->delete());

        return to_route('copies.index')->with('success', $copies->count().' exemplaire(s) supprimé(s).');
    }

    public function print(Copy $copy, BarcodeService $barcodes): View
    {
        $copy->load('book');

        return view('print.copy-label', ['copy' => $copy, 'codeSvg' => request()->boolean('qr') ? $barcodes->qrSvg($copy->barcode_value) : ($copy->barcode_symbology === BarcodeSymbology::Qr ? $barcodes->qrSvg($copy->barcode_value) : $barcodes->code128Svg($copy->barcode_value)), 'embedded' => request()->boolean('embedded')]);
    }

    public function printBulk(Request $request, BarcodeService $barcodes): View
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:copies,id']]);
        $order = array_flip($data['ids']);
        $copies = Copy::query()->whereKey($data['ids'])->with('book')->get()->sortBy(fn (Copy $copy) => $order[$copy->id])->values();

        return view('print.copy-labels', ['copies' => $copies, 'codes' => $copies->mapWithKeys(fn (Copy $copy) => [$copy->id => $barcodes->qrSvg($copy->barcode_value)])]);
    }

    public function downloadPdf(Request $request, BarcodeService $barcodes): HttpResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:copies,id']]);
        $order = array_flip($data['ids']);
        $copies = Copy::query()->whereKey($data['ids'])->with('book')->get()->sortBy(fn (Copy $copy) => $order[$copy->id])->values();
        $html = view('print.copy-labels-pdf', ['copies' => $copies, 'codes' => $copies->mapWithKeys(fn (Copy $copy) => [$copy->id => $barcodes->qrPngDataUri($copy->barcode_value)])])->render();
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="etiquettes-qr-edsp-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }
}
