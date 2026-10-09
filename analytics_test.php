<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$admin = User::where('email', 'admin@admin.com')->first();

$request = Illuminate\Http\Request::create('/api/analytics', 'GET');
$request->setUserResolver(fn() => $admin);
app()->instance('request', $request);

$resp = app()->make(App\Http\Controllers\AnalyticsController::class)->index();
$data = json_decode($resp->getContent(), true);

echo "HTTP: {$resp->status()}\n";
echo "performanceMetrics: " . json_encode($data['performanceMetrics']) . "\n";
echo "officerWorkload:\n";
foreach ($data['officerWorkload'] as $o) {
    echo "  {$o['name']} ({$o['role']}): assigned={$o['assigned']} completed={$o['completed']} pending={$o['pending']} rate={$o['completionRate']}%\n";
}
echo "topOfficers: " . json_encode($data['topOfficers']) . "\n";
echo "caseOutcomes: " . json_encode($data['caseOutcomes']) . "\n";
echo "categoryBreakdown: " . json_encode(array_map(fn($x) => ['name' => $x['name'], 'count' => $x['count'], 'percentage' => $x['percentage']], $data['categoryBreakdown'])) . "\n";

// Also verify ComplaintResource exposes progress
$request2 = Illuminate\Http\Request::create('/api/complaints', 'GET');
$request2->setUserResolver(fn() => $admin);
app()->instance('request', $request2);
$resp2 = app()->make(App\Http\Controllers\ComplaintController::class)->index();
$d2 = json_decode($resp2->getContent(), true);
echo "Complaints resource progress: ";
foreach (($d2['data'] ?? []) as $c) {
    echo "{$c['complainant_name']}={$c['progress_percent']}%({$c['progress_stage']}) ";
}
echo "\nDONE\n";
