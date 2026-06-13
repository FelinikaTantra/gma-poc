<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCompatibility;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $aiService;

    public function __construct(GeminiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index()
    {
        $products = Product::with('productCompatibilities')->get();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'external_product_id' => 'nullable|string|max:255',
            'compatibilities' => 'nullable|array',
            'compatibilities.*.brand' => 'nullable|string|max:255',
            'compatibilities.*.model' => 'nullable|string|max:255',
            'compatibilities.*.year' => 'nullable|string|max:255',
        ]);

        $companyId = \DB::table('companies')->first()->id ?? 1;

        $product = Product::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'external_product_id' => $validated['external_product_id'] ?? null,
        ]);

        if (!empty($validated['compatibilities'])) {
            foreach ($validated['compatibilities'] as $comp) {
                if (!empty($comp['brand']) || !empty($comp['model'])) {
                    $product->productCompatibilities()->create([
                        'vehicle_brand' => $comp['brand'] ?? null,
                        'vehicle_model' => $comp['model'] ?? null,
                        'vehicle_year' => $comp['year'] ?? null,
                    ]);
                }
            }
        }

        // Re-load relation to get correct embedding text
        $product->load('productCompatibilities');

        // Generate embedding
        $embeddingText = $this->getEmbeddingText($product);
        $vector = $this->aiService->generateEmbedding($embeddingText);

        if ($vector) {
            $product->update(['vector' => $vector]);
        }

        return response()->json($product->load('productCompatibilities'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'external_product_id' => 'nullable|string|max:255',
            'compatibilities' => 'nullable|array',
            'compatibilities.*.brand' => 'nullable|string|max:255',
            'compatibilities.*.model' => 'nullable|string|max:255',
            'compatibilities.*.year' => 'nullable|string|max:255',
        ]);

        $product->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'external_product_id' => $validated['external_product_id'] ?? null,
        ]);

        // Sync compatibilities
        $product->productCompatibilities()->delete();
        if (!empty($validated['compatibilities'])) {
            foreach ($validated['compatibilities'] as $comp) {
                if (!empty($comp['brand']) || !empty($comp['model'])) {
                    $product->productCompatibilities()->create([
                        'vehicle_brand' => $comp['brand'] ?? null,
                        'vehicle_model' => $comp['model'] ?? null,
                        'vehicle_year' => $comp['year'] ?? null,
                    ]);
                }
            }
        }

        // Re-load relation
        $product->load('productCompatibilities');

        // Re-generate embedding
        $embeddingText = $this->getEmbeddingText($product);
        $vector = $this->aiService->generateEmbedding($embeddingText);

        if ($vector) {
            $product->update(['vector' => $vector]);
        }

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['success' => true]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, ['name', 'price', 'stock', 'external_product_id', 'compatibilities']);
            
            // Sample rows
            fputcsv($file, [
                'Spion Carbon NMAX/XMAX',
                150000,
                50,
                'SP-001',
                'Yamaha:NMAX:2020-2024;Yamaha:XMAX:2021-2024'
            ]);

            fputcsv($file, [
                'Oli Shell Advance Matic',
                65000,
                100,
                'OL-002',
                'Honda:Vario:All;Yamaha:NMAX:All'
            ]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['success' => false, 'message' => 'Failed to open uploaded file.'], 400);
        }

        // Header mapping
        $headers = fgetcsv($handle, 1000, ',');
        if (!$headers || count($headers) < 3) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Invalid template format. Header row mismatch.'], 400);
        }

        // Standardize header names
        $headers = array_map(function($h) {
            return trim(strtolower($h));
        }, $headers);

        $companyId = \DB::table('companies')->first()->id ?? 1;
        $importCount = 0;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Match fields by headers index
            $data = [];
            foreach ($headers as $index => $header) {
                if (isset($row[$index])) {
                    $data[$header] = trim($row[$index]);
                }
            }

            if (empty($data['name']) || !isset($data['price']) || !isset($data['stock'])) {
                continue; // Skip invalid rows
            }

            // Update or Create by external_product_id (if provided) or name
            $product = null;
            if (!empty($data['external_product_id'])) {
                $product = Product::where('external_product_id', $data['external_product_id'])->first();
            }

            if (!$product) {
                $product = Product::where('name', $data['name'])->first();
            }

            if ($product) {
                $product->update([
                    'name' => $data['name'],
                    'price' => (float)$data['price'],
                    'stock' => (int)$data['stock'],
                    'external_product_id' => $data['external_product_id'] ?: null,
                ]);
            } else {
                $product = Product::create([
                    'company_id' => $companyId,
                    'name' => $data['name'],
                    'price' => (float)$data['price'],
                    'stock' => (int)$data['stock'],
                    'external_product_id' => $data['external_product_id'] ?: null,
                ]);
            }

            // Sync compatibilities
            $product->productCompatibilities()->delete();
            if (!empty($data['compatibilities'])) {
                $compArray = explode(';', $data['compatibilities']);
                foreach ($compArray as $compStr) {
                    $parts = explode(':', trim($compStr));
                    if (count($parts) >= 2) {
                        $product->productCompatibilities()->create([
                            'vehicle_brand' => trim($parts[0]),
                            'vehicle_model' => trim($parts[1]),
                            'vehicle_year' => isset($parts[2]) ? trim($parts[2]) : null,
                        ]);
                    }
                }
            }

            // Re-load relations
            $product->load('productCompatibilities');

            // Generate vector embedding
            $embeddingText = $this->getEmbeddingText($product);
            $vector = $this->aiService->generateEmbedding($embeddingText);

            if ($vector) {
                $product->update(['vector' => $vector]);
            }

            $importCount++;
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => "Successfully imported {$importCount} products.",
            'count' => $importCount
        ]);
    }

    private function getEmbeddingText(Product $product)
    {
        $text = "Product: " . $product->name . " | Price: Rp" . number_format($product->price, 0, ',', '.') . " | Stock: " . $product->stock;
        
        $compatibilities = $product->productCompatibilities;
        if ($compatibilities->isNotEmpty()) {
            $compatText = $compatibilities->map(function ($c) {
                return "{$c->vehicle_brand} {$c->vehicle_model} ({$c->vehicle_year})";
            })->implode(', ');
            $text .= " | Compatible with: " . $compatText;
        }

        return $text;
    }
}
