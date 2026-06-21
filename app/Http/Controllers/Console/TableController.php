<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Outlet;
use App\Models\Table;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use App\DataTables\TableDataTable;

class TableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TableDataTable $dataTable)
    {
        return $dataTable->render('console.tables.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $outlets = Outlet::all();
        return view('console.tables.create', compact('outlets'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTableRequest $request)
    {
        $data = $request->validated();
        $data['table_number'] = $data['code'];

        // Generate QR code filename and path
        $qrCodeName = 'qr-' . $data['code'] . '.svg';
        $directory = 'public/qrcodes';
        $filePath = $directory . '/' . $qrCodeName;

        // Ensure directory exists
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        // Generate QR Code content
        $qrContent = QrCode::format('svg')
            ->size(300)
            ->generate(route('order.menu', ['table_code' => $data['code']]));

        // Save QR Code to storage
        Storage::put($filePath, $qrContent);

        // Save relative URL (for use in views)
        $data['qr_code_url'] = 'qrcodes/' . $qrCodeName;
        $data['table_code'] = $data['table_number'];

        Table::create($data);

        return redirect()->route('tables.index')->with('success', 'Table created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Table $table)
    {
        return view('console.tables.show', compact('table'));
    }

    /**
     * Display master QR code (single QR for all tables).
     */
    public function masterQr()
    {
        return view('console.tables.master-qr');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Table $table)
    {
        $outlets = Outlet::all();
        return view('console.tables.edit', compact('table', 'outlets'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTableRequest $request, Table $table)
    {
        $data = $request->validated();
        $data['table_number'] = $data['code'];

        // Check if table code has changed
        if ($table->table_number !== $data['code']) {
            // Delete old QR code if exists
            if ($table->qr_code_url && Storage::disk('public')->exists($table->qr_code_url)) {
                Storage::disk('public')->delete($table->qr_code_url);
            }

            // Prepare new QR code
            $qrCodeName = 'qr-' . $data['code'] . '.svg';
            $directory = 'public/qrcodes';
            $storagePath = $directory . '/' . $qrCodeName;

            // Ensure QR code directory exists
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Generate QR content
            $qrContent = QrCode::format('svg')
                ->size(300)
                ->generate(route('order.menu', ['table_code' => $data['code']]));

            // Store QR code
            Storage::put($storagePath, $qrContent);

            // Save the path for DB (excluding 'public/')
            $data['qr_code_url'] = 'qrcodes/' . $qrCodeName;
        }

        $table->update($data);

        return redirect()->route('tables.index')->with('success', 'Table updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Table $table)
    {
        if ($table->qr_code_url) {
            Storage::disk('public')->delete($table->qr_code_url);
        }
        $table->delete();

        return redirect()->route('tables.index')->with('success', 'Table deleted successfully.');
    }
}
