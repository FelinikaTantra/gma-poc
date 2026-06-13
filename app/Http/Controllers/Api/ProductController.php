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
