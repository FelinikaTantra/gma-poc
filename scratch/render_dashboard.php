<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Login as Super Admin
\Auth::loginUsingId(1);

// Render the dashboard view
try {
    $html = view('dashboard')->render();
    file_put_contents(__DIR__ . '/rendered_dashboard.html', $html);
    echo "Successfully rendered dashboard view to scratch/rendered_dashboard.html\n";
} catch (\Exception $e) {
    echo "Error rendering dashboard view: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
