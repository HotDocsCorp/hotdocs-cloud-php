hotdocs-cloud-php
=================

The hotdocs-cloud-php script is a client wrapper for the HotDocs Cloud Services REST API.  It currently supports the Cloud Services calls necessary for creating and resuming an embedded HotDocs session.

To create an embedded session:
```php
$client = new Client('SUBSCRIBER_ID', 'SIGNING_KEY');
$request = new CreateSessionRequest('Package ID', 'C:\myfilepath\package.hdpkg');
$sessionId = $client->sendRequest($request);
```


