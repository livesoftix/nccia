<?php
$url = 'https://api.github.com/repos/livesoftix/NCCIA/commits?per_page=100';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json', 'User-Agent: NCCIA-Recovery'],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo "HTTP $code\n";
    echo $resp . "\n";
    exit(1);
}

$commits = json_decode($resp, true);
echo "Found " . count($commits) . " commits on main:\n";
foreach (array_slice($commits, 0, 10) as $c) {
    echo $c['sha'] . ' - ' . substr($c['commit']['message'], 0, 80) . "\n";
}
echo "...\n";
echo "Oldest visible: " . (count($commits) > 0 ? end($commits)['sha'] . ' - ' . substr(end($commits)['commit']['message'], 0, 60) : 'none') . "\n";