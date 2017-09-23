<?php

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required(['CLOUDFLARE_EMAIL', 'CLOUDFLARE_KEY', 'DOMAIN', 'SUBDOMAIN', 'RECORD_TYPE']);
if (empty($_ENV['PROXIED'])) {
    $_ENV['PROXIED'] = false;
} else {
    $_ENV['PROXIED'] = (bool) $_ENV['PROXIED'];
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

try {
    $cfCall = $cfZone->getZoneID($_ENV['DOMAIN']);
    $zoneId = $cfCall;

    $cfCall = $cfDns->listRecords($zoneId, '', $_ENV['SUBDOMAIN']);
    if (count($cfCall->result) !== 1) {
        $climate->error(sprintf('Record %2$s not found in zone %1$s', $_ENV['DOMAIN'], $_ENV['SUBDOMAIN']));
        $climate->error('Please first add the record manually. This will be fixed in a future version.');
        die();
    }

    $recordId = $cfCall->result[0]->id;

    $cfCall = $cfDns->updateRecordDetails($zoneId, $recordId, [
        'type'      => $_ENV['RECORD_TYPE'],
        'content'   => $publicIp,
        'proxiable' => true,
        'proxied'   => $_ENV['PROXIED'],
    ]
);
} catch (Exception $e) {
    $climate->error('Failed to update '.$_ENV['SUBDOMAIN']);
    $climate->error($e->getMessage());
    if (property_exists($cfCall, 'errors')) {
        $climate->dump($cfCall->errors);
    }
}
    $climate->green('Successfully updated '.$_ENV['SUBDOMAIN']);
