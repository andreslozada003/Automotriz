<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\ProductBarcodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ProductImportController extends Controller
{
    public function create()
    {
        return view('products.import');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
        ]);

        $rows = $this->rowsFromFile($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        $products = $this->prepareProducts($rows);

        DB::transaction(function () use ($products): void {
            $categories = [];
            foreach ($products as $attributes) {
                $categoryName = $attributes['category_name'];
                if (! isset($categories[Str::lower($categoryName)])) {
                    $categories[Str::lower($categoryName)] = Category::whereRaw('LOWER(name) = ?', [Str::lower($categoryName)])->first()
                        ?? Category::create(['name' => $categoryName, 'active' => true]);
                }
                $attributes['category_id'] = $categories[Str::lower($categoryName)]->id;
                unset($attributes['category_name']);
                $product = Product::create($attributes);
                $product->update(['sku' => app(BarcodeService::class)->generateSku($product)]);
                app(ProductBarcodeService::class)->syncPrimaryBarcode($product, null, 'CODE128', auth()->id());
            }
        });

        return redirect()->route('products.index')->with('success', count($products).' productos cargados y sus códigos de barras fueron generados.');
    }

    public function template()
    {
        $path = tempnam(sys_get_temp_dir(), 'plantilla-productos-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationships());
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet());
        $zip->close();

        return response()->download($path, 'plantilla-productos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function prepareProducts(array $rows): array
    {
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene productos para importar.']);
        }

        $headers = array_map(fn ($header) => $this->header($header), array_shift($rows));
        $columns = array_flip($headers);
        foreach (['codigo', 'descripcion', 'cantidad', 'valorunitario', 'margen', 'categoria', 'stockminimo'] as $required) {
            if (! array_key_exists($required, $columns)) {
                throw ValidationException::withMessages(['file' => "Falta la columna requerida: {$required}."]);
            }
        }

        $products = [];
        foreach ($rows as $index => $row) {
            if (! array_filter($row, fn ($value) => filled($value))) {
                continue;
            }

            $line = $index + 2;
            $code = trim((string) ($row[$columns['codigo']] ?? ''));
            $name = trim((string) ($row[$columns['descripcion']] ?? ''));
            $stock = $this->number($row[$columns['cantidad']] ?? null);
            $cost = $this->number($row[$columns['valorunitario']] ?? null);
            $margin = $this->number($row[$columns['margen']] ?? null);
            $category = trim((string) ($row[$columns['categoria']] ?? ''));
            $minStock = $this->number($row[$columns['stockminimo']] ?? null);

            $errors = [];
            if ($code === '') {
                $errors[] = 'Código';
            }
            if ($name === '') {
                $errors[] = 'Descripción';
            }
            if ($stock === null || $stock < 0 || floor($stock) !== $stock) {
                $errors[] = 'Cantidad (entero igual o mayor a 0)';
            }
            if ($cost === null || $cost < 0) {
                $errors[] = 'Costo neto sin IVA';
            }
            if (! in_array($margin, [30.0, 40.0, 50.0, 80.0], true)) {
                $errors[] = 'Margen (solo 30, 40, 50 u 80)';
            }
            if ($category === '') {
                $errors[] = 'Categoría';
            }
            if ($minStock === null || $minStock < 0 || floor($minStock) !== $minStock) {
                $errors[] = 'Stock mínimo (entero igual o mayor a 0)';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(['file' => "Fila {$line}: ".implode(', ', $errors).'.']);
            }
            $products[] = [
                'category_name' => $category,
                'supplier_id' => null,
                'code' => $code,
                'name' => $name,
                'description' => $name,
                'purchase_price' => $cost,
                'purchase_tax_rate' => 19,
                'profit_margin' => $margin,
                'sale_price' => round($cost * 1.19 * (1 + ($margin / 100)), 2),
                'tax_rate' => 19,
                'stock' => (int) $stock,
                'min_stock' => (int) $minStock,
                'active' => true,
            ];
        }

        if ($products === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene filas válidas.']);
        }

        return $products;
    }

    private function rowsFromFile(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'r');
            $rows = [];
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                if (count($row) === 1) {
                    $row = str_getcsv($row[0], ',');
                }
                $rows[] = $row;
            }
            fclose($handle);

            return $rows;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo Excel.']);
        }
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $sheetXml) {
            throw ValidationException::withMessages(['file' => 'El archivo Excel no contiene una primera hoja válida.']);
        }

        $shared = [];
        if ($sharedXml) {
            $xml = simplexml_load_string($sharedXml);
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xml->xpath('//x:si') ?: [] as $item) {
                $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $shared[] = trim(implode('', array_map('strval', $item->xpath('.//x:t') ?: [])));
            }
        }
        $xml = simplexml_load_string($sheetXml);
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xml->xpath('//x:sheetData/x:row') as $row) {
            $values = [];
            $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($row->xpath('./x:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                preg_match('/[A-Z]+/', (string) $cell['r'], $match);
                $column = $this->columnNumber($match[0] ?? 'A');
                $value = (string) ($cell->xpath('./x:v')[0] ?? '');
                $type = (string) $cell['t'];
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = implode('', array_map('strval', $cell->xpath('./x:is//x:t') ?: []));
                }
                $values[$column] = $value;
            }
            $rows[] = $values === [] ? [] : array_values(array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values));
        }

        return $rows;
    }

    private function header(mixed $value): string
    {
        $value = Str::ascii(Str::lower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', '', $value);

        return match ($value) {
            'codigo', 'cod' => 'codigo',
            'descripcion', 'nombre', 'producto' => 'descripcion',
            'cantidad', 'stock' => 'cantidad',
            'valorunitario', 'valorunitariocosto', 'precio', 'preciounitario', 'valor', 'costonetosiniva', 'costoneto', 'costosiniva' => 'valorunitario',
            'margen', 'margenganancia', 'porcentajemargen', 'utilidad' => 'margen',
            'categoria', 'category' => 'categoria',
            'stockminimo', 'minstock', 'existenciaminima' => 'stockminimo',
            default => $value,
        };
    }

    private function number(mixed $value): ?float
    {
        $value = trim(str_replace(['$', '%', ' ', "\xc2\xa0"], '', (string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $value = strlen(end($parts)) === 2 ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + (ord($letter) - 64);
        }

        return $number - 1;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function xlsxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF595959"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyAlignment="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs></styleSheet>';
    }

    private function xlsxSheet(): string
    {
        // The template contains only the fields that the importer accepts.
        // Each label is written to an individual XLSX cell below, so Excel
        // renders them as separate columns rather than one combined value.
        $headers = ['Código', 'Descripción', 'Cantidad', 'Costo Neto Sin IVA', 'Margen (%)', 'Categoría', 'Stock Mínimo'];
        $cells = [];
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $cells[] = '<c r="'.$column.'1" t="inlineStr" s="1"><is><t>'.htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><selection activeCell="A2" sqref="A2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/><cols><col min="1" max="1" width="10" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="38" customWidth="1"/><col min="4" max="4" width="12" customWidth="1"/><col min="5" max="5" width="12" customWidth="1"/><col min="6" max="6" width="19" customWidth="1"/><col min="7" max="8" width="12" customWidth="1"/><col min="9" max="10" width="15" customWidth="1"/><col min="11" max="11" width="16" customWidth="1"/><col min="12" max="12" width="16" customWidth="1"/><col min="13" max="13" width="20" customWidth="1"/></cols><sheetData><row r="1" ht="42" customHeight="1">'.implode('', $cells).'</row></sheetData></worksheet>';
    }
}
