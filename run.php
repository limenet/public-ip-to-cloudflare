<?php

require 'vendor/autoload.php';

$climate = new League\CLImate\CLImate();

$climate->out('--- public-ip-to-cloudflare ---');

use \Curl\Curl;

$curlIP = new Curl();
$curlCF = new Curl();
$curlCF->setOpt(CURLOPT_CAINFO, __DIR__.'/cacert.pem');

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required(['CLOUDFLARE_EMAIL', 'CLOUDFLARE_KEY', 'DOMAIN', 'SUBDOMAIN', 'RECORD_TYPE']);

$publicIp = $curlIP->get('http://ipecho.net/plain');

$climate->out('Your public IP address is '.$publicIp);


$curlCF->setHeader('X-Auth-Key', $_ENV['CLOUDFLARE_KEY']);
$curlCF->setHeader('X-Auth-Email', $_ENV['CLOUDFLARE_EMAIL']);
$curlCF->setHeader('Content-Type', 'application/json');

$curlCF->get('https://api.cloudflare.com/client/v4/zones',
    [
        'name' => $_ENV['DOMAIN'],
    ]
);

$zoneId = $curlCF->response->result[0]->id;


$curlCF->get('https://api.cloudflare.com/client/v4/zones/'.$zoneId.'/dns_records',
    [
        'name' => $_ENV['SUBDOMAIN'],
    ]
);

$recordId = $curlCF->response->result[0]->id;

$curlCF->put('https://api.cloudflare.com/client/v4/zones/'.$zoneId.'/dns_records/'.$recordId,
    [
        'name'      => $_ENV['SUBDOMAIN'],
        'zone_name' => $_ENV['DOMAIN'],
        'content'   => $publicIp,
        'type'      => $_ENV['RECORD_TYPE'],
    ]
);

if ($curlCF->response->success) {
    $climate->green('Successfully updated '.$_ENV['SUBDOMAIN']);
} else {
    $climate->error('Failed to update '.$_ENV['SUBDOMAIN']);
    $errors = [];
    $i = 0;
    foreach($curlCF->response->errors as $error) {
        $errors[$i] = $error->message.': ';
        $chains     = [];

        foreach ($error->error_chain as $chain) {
            $chains[] = $chain->message;
        }

        $errors[$i] .= implode(', ', $chains);
        $i++;
    }

    $climate->error($errors);
}