<?php

namespace App\DataTables;

use App\Models\Product;
use App\Models\Outlet;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class ProductDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('action', 'console.products.action')
            ->editColumn('outlet_id', function (Product $product) {
                return $product->outlet->name ?? 'N/A';
            })
            ->editColumn('category_id', function (Product $product) {
                return $product->category->name ?? 'N/A';
            })
            ->editColumn('price', function (Product $product) {
                return 'Rp ' . number_format($product->price, 0, ',', '.');
            })
            ->editColumn('is_available', function (Product $product) {
                return $product->is_available ? '<span class="badge bg-success">Tersedia</span>' : '<span class="badge bg-danger">Tidak Tersedia</span>';
            })
            // ->editColumn('image_url', function (Product $product) {
            //     return $product->image_url ? '<img src="' . asset($product->image_url) . '" alt="Product Image" width="50">' : 'Tidak Ada Gambar';
            // })
            ->rawColumns(['action', 'is_available', 'image_url']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Product $model): QueryBuilder
    {
        return $model->newQuery()->with(['outlet', 'category'])->orderBy('created_at', 'desc');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dom = '<"row mx-1"' .
            '<"col-sm-12 col-md-3 mt-5 mt-md-0" l>' .
            '<"col-sm-12 col-md-9"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-md-end justify-content-center flex-wrap me-1"<"me-4"f>B>>' .
            '>t' .
            '<"row mx-2"' .
            '<"col-sm-12 col-md-6"i>' .
            '<"col-sm-12 col-md-6"p>' .
            '>';

        $language = [
            'sLengthMenu' => 'Tampilkan _MENU_',
            'search' => '',
            'searchPlaceholder' => 'Cari Produk...',
            'paginate' => [
                'next' => '<i class="ri-arrow-right-s-line"></i>',
                'previous' => '<i class="ri-arrow-left-s-line"></i>'
            ]
        ];

        $buttons = [
            [
                'text' => '<i class="ri-add-line me-0 me-sm-1"></i><span class="d-none d-sm-inline-block">Tambah Produk</span>',
                'className' => 'add-new btn btn-primary mb-5 mb-md-0 me-3 waves-effect waves-light',
                'action' => 'function (e, dt, node, config) { window.location = "' . route('products.create') . '"; }'
            ],
            [
                'text' => '<i class="ri-refresh-line me-0 me-sm-1"></i><span class="d-none d-sm-inline-block">Muat Ulang</span>',
                'className' => 'btn btn-secondary mb-5 mb-md-0 waves-effect waves-light',
                'action' => 'function (e, dt, node, config) { dt.ajax.reload(); $("#products-table_filter input").val("").keyup(); }'
            ]
        ];

        return $this->builder()
            ->setTableId('products-table')
            ->columns($this->getColumns())
            ->parameters([
                'order' => [[0, 'desc']],
                'dom' => $dom,
                'language' => $language,
                'buttons' => $buttons,
                'responsive' => true,
                'autoWidth' => false,
            ])
            ->ajax([
                'url'  => route('products.index'),
                'type' => 'GET',
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('DT_RowIndex')->title('#')->orderable(false)->searchable(false),
            // Column::make('image_url')->title('Gambar'),
            Column::make('name')->title('Nama Produk'),
            Column::make('category_id')->title('Kategori'),
            Column::make('price')->title('Harga'),
            Column::make('is_available')->title('Ketersediaan'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center')
                ->title('Aksi'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Products_' . date('YmdHis');
    }
}
