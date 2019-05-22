<?php

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required(['CLOUDFLARE_EMAIL', 'CLOUDFLARE_KEY', 'DOMAIN', 'SUBDOMAIN', 'RECORD_TYPE']);
if (empty($_ENV['PROXIED'])) {
    $_ENV['PROXIED'] = false;
} else {
    $_ENV['PROXIED'] = filter_var($_ENV['PROXIED'], FILTER_VALIDATE_BOOLEAN);
}
$climate = new League\CLImate\CLImate();

$climate->out('--- public-ip-to-cloudflare ---');

use Curl\Curl;

$curlIP = new Curl();

$cfKey = new \Cloudflare\API\Auth\APIKey($_ENV['CLOUDFLARE_EMAIL'], $_ENV['CLOUDFLARE_KEY']);
$cfAdapter = new Cloudflare\API\Adapter\Guzzle($cfKey);
$cfDns = new \Cloudflare\API\Endpoints\DNS($cfAdapter);
$cfZone = new \Cloudflare\API\Endpoints\Zones($cfAdapter);

$publicIp = $curlIP->get('http://ipecho.net/plain');

$climate->out('Your public IP address is '.$publicIp);

$exceptionThrown = false;

try {
    $cfCall = $cfZone->getZoneID($_ENV['DOMAIN']);
    $zoneId = $cfCall;

    $cfCall = $cfDns->listRecords($zoneId, 'A', $_ENV['SUBDOMAIN']);

    if (count($cfCall->result) !== 1) {
        $climate->error(sprintf('Record %2$s not found in zone %1$s', $_ENV['DOMAIN'], $_ENV['SUBDOMAIN']));
        $climate->error('Please first add the record manually. This will be fixed in a future version.');
        die();
    }

    $recordId = $cfCall->result[0]->id;
    $currentIp = $cfCall->result[0]->content;

    $cfCall = $cfDns->updateRecordDetails($zoneId, $recordId, [
        'type'    => $_ENV['RECORD_TYPE'],
        'name'    => $_ENV['SUBDOMAIN'],
        'content' => $publicIp,
        'proxied' => $_ENV['PROXIED'],
        ]
    );

    if($currentIp !== $publicIp) {
        $climate->info(sprintf('IP has changed from %s to %s', $currentIp, $publicIp));
        if(array_key_exists('PUSHOVER_API_TOKEN', $_ENV) && array_key_exists('PUSHOVER_USER', $_ENV)){
            $curlPushover = new Curl();
            $curlPushover->post('https://api.pushover.net/1/messages.json', [
                'token' => $_ENV['PUSHOVER_API_TOKEN'],
                'user' => $_ENV['PUSHOVER_USER'],
                'title' => sprintf('IP change for %s %s', $_ENV['RECORD_TYPE'], $_ENV['SUBDOMAIN']),
                'message' => sprintf("IP has changed from %s to %s", $currentIp, $publicIp),
            ]);
        }
    }
} catch (Exception $e) {
    $exceptionThrown = true;

    $climate->error('Failed to update '.$_ENV['SUBDOMAIN']);
    $climate->error($e->getMessage());
    if (isset($cfCall) && property_exists($cfCall, 'errors')) {
        $climate->dump($cfCall->errors);
    }
}

if (!$exceptionThrown) {
    $climate->green('Successfully updated '.$_ENV['SUBDOMAIN']);
}
