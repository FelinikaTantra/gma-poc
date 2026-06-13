<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Product;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VectorSearchTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GeminiService::class);

        // Seed settings and company
        \DB::table('companies')->insert([
            'id' => 1,
            'name' => 'Divine Project',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AiSetting::firstOrCreate([
            'id' => 1
        ], [
            'full_control' => false,
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7,
            'openai_token' => 'mock-token'
        ]);
    }

    public function test_vector_similarity_finds_relevant_products()
    {
        // Product 1: Spion Motor (similar to motor mirrors)
        // Let's mock a vector [0.9, 0.1, 0.0]
        $p1 = Product::create([
            'company_id' => 1,
            'name' => 'Spion Motor Carbon',
            'price' => 150000,
            'stock' => 10,
            'vector' => [0.9, 0.1, 0.0]
        ]);

        // Product 2: Oli Mesin (similar to oil)
        // Let's mock a vector [0.0, 0.1, 0.9]
        $p2 = Product::create([
            'company_id' => 1,
            'name' => 'Oli Mesin Matic Motul',
            'price' => 85000,
            'stock' => 50,
            'vector' => [0.0, 0.1, 0.9]
        ]);

        // Mock OpenAI Embeddings API returning a vector similar to Oli ([0.0, 0.0, 1.0])
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => [0.05, 0.05, 0.9]
                    ]
                ]
            ], 200)
        ]);

        $relevantProducts = $this->service->getRelevantProducts('beli oli untuk motor matic', 2);

        $this->assertNotEmpty($relevantProducts);
        // The first result should be the Oli Mesin (p2) because its vector [0.0, 0.1, 0.9] is highly similar to [0.05, 0.05, 0.9]
        $this->assertEquals($p2->id, $relevantProducts[0]->id);
    }
}
