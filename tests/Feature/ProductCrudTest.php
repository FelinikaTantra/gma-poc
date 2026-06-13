<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Channel;
use App\Models\Product;
use App\Models\ProductCompatibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default settings and company
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

    public function test_can_list_products()
    {
        $product = Product::create([
            'company_id' => 1,
            'name' => 'Test Product',
            'price' => 50000,
            'stock' => 10,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Test Product',
                'price' => 50000,
                'stock' => 10,
            ]);
    }

    public function test_can_create_product_with_vector_embedding()
    {
        // Mock OpenAI Embeddings endpoint
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3]
                    ]
                ]
            ], 200)
        ]);

        $payload = [
            'name' => 'Spion Yamaha',
            'price' => 150000,
            'stock' => 20,
            'external_product_id' => 'EXT-SP-10',
            'compatibilities' => [
                ['brand' => 'Yamaha', 'model' => 'NMAX', 'year' => '2022']
            ]
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Spion Yamaha',
                'price' => 150000,
                'stock' => 20,
                'external_product_id' => 'EXT-SP-10'
            ]);

        // Check if compatibilities were created
        $this->assertDatabaseHas('product_compatibility', [
            'vehicle_brand' => 'Yamaha',
            'vehicle_model' => 'NMAX',
            'vehicle_year' => '2022'
        ]);

        // Check if vector was generated and saved
        $product = Product::where('name', 'Spion Yamaha')->first();
        $this->assertNotNull($product->vector);
        $this->assertEquals([0.1, 0.2, 0.3], $product->vector);

        // Verify request payload sent to OpenAI Embeddings
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/embeddings') &&
                $request['model'] === 'text-embedding-3-small' &&
                str_contains($request['input'], 'Spion Yamaha') &&
                str_contains($request['input'], 'Yamaha NMAX (2022)');
        });
    }

    public function test_can_update_product_and_regenerate_vector()
    {
        $product = Product::create([
            'company_id' => 1,
            'name' => 'Oli Yamalube',
            'price' => 60000,
            'stock' => 15,
            'vector' => [0.1, 0.1, 0.1]
        ]);

        // Mock OpenAI Embeddings
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => [0.9, 0.8, 0.7]
                    ]
                ]
            ], 200)
        ]);

        $payload = [
            'name' => 'Oli Yamalube Super',
            'price' => 65000,
            'stock' => 25,
            'external_product_id' => 'EXT-OLI-1',
            'compatibilities' => []
        ];

        $response = $this->putJson("/api/products/{$product->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Oli Yamalube Super',
                'price' => 65000,
                'stock' => 25
            ]);

        // Check if vector was updated
        $product->refresh();
        $this->assertEquals([0.9, 0.8, 0.7], $product->vector);
    }

    public function test_can_delete_product()
    {
        $product = Product::create([
            'company_id' => 1,
            'name' => 'Oli Yamalube',
            'price' => 60000,
            'stock' => 15,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id
        ]);
    }

    public function test_can_download_csv_template()
    {
        $response = $this->get('/api/products/template');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('name,price,stock,external_product_id,compatibilities', $content);
        $this->assertStringContainsString('Spion Carbon NMAX/XMAX', $content);
    }

    public function test_can_import_products_from_csv()
    {
        // Mock OpenAI Embeddings
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => [0.5, 0.6, 0.7]
                    ]
                ]
            ], 200)
        ]);

        // Create temporary CSV file content
        $csvContent = "name,price,stock,external_product_id,compatibilities\n";
        $csvContent .= "Kampas Rem Depan,50000,10,KMP-101,\"Honda:Vario:2020-2023;Yamaha:NMAX:2021\"\n";
        $csvContent .= "Oli Shell Matic,80000,5,OLI-202,\"Universal:Matic:All\"\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'products.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->postJson('/api/products/import', [
            'file' => $uploadedFile
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2
            ]);

        // Check if database records were inserted
        $this->assertDatabaseHas('products', [
            'name' => 'Kampas Rem Depan',
            'price' => 50000,
            'stock' => 10,
            'external_product_id' => 'KMP-101'
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Oli Shell Matic',
            'price' => 80000,
            'stock' => 5,
            'external_product_id' => 'OLI-202'
        ]);

        // Check compatibility mapping
        $this->assertDatabaseHas('product_compatibility', [
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Vario',
            'vehicle_year' => '2020-2023'
        ]);

        // Check if vector was generated and saved
        $product = Product::where('external_product_id', 'KMP-101')->first();
        $this->assertEquals([0.5, 0.6, 0.7], $product->vector);

        unlink($tempFile);
    }
}
