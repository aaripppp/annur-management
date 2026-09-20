<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

// Build request FIRST so auth can resolve it
$request = Request::create('/siswa/348', 'GET');

// Bootstrap with the request attached
$kernel->bootstrap();
app('auth')->setRequest($request);

// Login
$user = User::first();
auth()->loginUsingId($user->id);

$response = $kernel->handle($request);
echo 'Status: '.$response->getStatusCode()."\n";
if ($response->getStatusCode() >= 400) {
    echo substr($response->getContent(), 0, 3000)."\n";
}
