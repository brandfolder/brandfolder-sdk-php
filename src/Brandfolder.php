<?php

namespace Brandfolder;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

/**
 * Brandfolder library.
 *
 * @package Brandfolder
 */
class Brandfolder {

  const VERSION = '0.1.0';

  /**
   * API version.
   *
   * @var string $version
   */
  public $version = self::VERSION;

  /**
   * The status code of the most recent operation, if applicable.
   *
   * @var int $status
   */
  public $status;

  /**
   * A useful message pertaining to the most recent operation, if applicable.
   *
   * @var string $message
   */
  public $message;

  /**
   * HTTP client.
   *
   * @var ClientInterface $client
   */
  protected $client;

  /**
   * The REST API endpoint.
   *
   * @var string $endpoint
   */
  protected $endpoint = 'https://brandfolder.com/api/v4';

  /**
   * The Brandfolder API key with which to authenticate
   * (used as a bearer token).
   *
   * @var string $api_key
   */
  private $api_key;

  /**
   * The Brandfolder to use for Brandfolder-specific requests, when no other
   * Brandfolder is specified.
   *
   * @var string $default_brandfolder_id
   * 
   * @todo setBrandfolder() method.
   */
  public $default_brandfolder_id;

  /**
   * The collection to use for collection-specific requests, when no other
   * collection is specified.
   *
   * @var string $default_collection_id
   * 
   * @todo setCollection() method.
   */
  public $default_collection_id;

  /**
   * Brandfolder constructor.
   *
   * @param string $api_key
   * @param \GuzzleHttp\ClientInterface|NULL $client
   */
  public function __construct($api_key, $brandfolder_id = NULL, ClientInterface $client = NULL) {
    $this->api_key = $api_key;

    if (!is_null($brandfolder_id)) {
      $this->default_brandfolder_id = $brandfolder_id;
    }
    
    if (is_null($client)) {
      $client = new Client();
    }
    $this->client = $client;
  }

  /**
   * Gets Brandfolder Organizations to which the current user belongs.
   *
   * @return ResponseInterface
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-organizations
   */
  public function getOrganizations($query_params = []) {
    return $this->request('GET', '/organizations', $query_params);
  }

  /**
   * Gets Brandfolders to which the current user has access.
   *
   * @param array $query_params
   * @return array
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-brandfolders
   */
  public function getBrandfolders($query_params = []) {
    $response = $this->request('GET', '/brandfolders', $query_params);
    $this->status = $response->getStatusCode();
    if ($this->status == 200) {
      $brandfolders = [];
      $content = \GuzzleHttp\json_decode($response->getBody()->getContents());
      if (isset($content->data)) {
        foreach ($content->data as $bf_data) {
          $brandfolders[$bf_data->id] = $bf_data->attributes->name;
        }
      }

      return $brandfolders;
    }
    else {
      $this->message = $response->getReasonPhrase();

      return FALSE;
    }
  }

  /**
   * Gets Collections to which the current user has access.
   *
   * @param array $query_params
   * @return ResponseInterface
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-collections
   */
  public function getCollectionsForUser($query_params = []) {
    return $this->request('GET', '/collections', $query_params);
  }

  /**
   * Gets Collections belonging to a certain Brandfolder.
   *
   * @param array $query_params
   * @return bool|array
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-collections
   */
  public function getCollectionsInBrandfolder($brandfolder_id = NULL, $query_params = []) {
    if (is_null($brandfolder_id)) {
      // @todo $this->getBrandfolder().
      $brandfolder_id = $this->default_brandfolder_id;
    }

    $response = $this->request('GET', "/brandfolders/{$brandfolder_id}/collections", $query_params);
    $this->status = $response->getStatusCode();
    if ($this->status == 200) {
      $collections = [];
      $content = \GuzzleHttp\json_decode($response->getBody()->getContents());
      if (isset($content->data)) {
        foreach ($content->data as $collection_data) {
          $collections[$collection_data->id] = $collection_data->attributes->name;
        }
      }

      return $collections;
    }
    else {
      $this->message = $response->getReasonPhrase();

      return FALSE;
    }
  }

  /**
   * Fetches an individual asset.
   *
   * @param $asset_id
   * @param array $query_params
   *
   * @return bool|mixed
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?python#fetch-an-asset
   */
  public function fetchAsset($asset_id, $query_params = []) {
    // @todo: Error handling, centralized.
    try {
      $response = $this->request('GET', "/assets/$asset_id", $query_params);

      $this->status = $response->getStatusCode();
      if ($this->status == 200) {
        $data = \GuzzleHttp\json_decode($response->getBody()->getContents());

        return $data;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
  }

  /**
   * Lists multipls assets.
   *
   * @param array $query_params
   *
   * @return bool|mixed
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-assets
   *
   * @todo: assets within Brandfolder vs collection vs org
   */
  public function listAssets($query_params = [], $collection = NULL) {
    // @todo: Error handling, centralized.
    try {
      if (isset($this->default_brandfolder_id)) {
        if (is_null($collection)) {
          $collection = $this->default_collection_id;
        }
        if (is_null($collection)) {
          $endpoint = "/brandfolders/{$this->default_brandfolder_id}/assets";
        }
        else {
          $endpoint = "/collections/$collection/assets";
        }
        $response = $this->request('GET', $endpoint, $query_params);

        $this->status = $response->getStatusCode();
        if ($this->status == 200) {
          $data = \GuzzleHttp\json_decode($response->getBody()->getContents());

          return $data->data;
        }
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
  }

  /**
   * Makes a request to the Brandfolder API.
   *
   * @param string $method
   *  The HTTP method to use for the request.
   * @param string $path
   *  The unique component of the API endpoint to use for this request.
   * @param array|null $query_params
   *  Associative array of URL query parameters to add to the request.
   * @param array|null $body
   *  Associative array of data to be sent as the body of the requests. This
   *  will be converted to JSON.
   *
   * @return ResponseInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function request($method, $path, $query_params = [], $body = NULL) {

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->api_key,
        'Host' => 'brandfolder.com',
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
      ],
    ];

    if (count($query_params) > 0) {
      $options['query'] = $query_params;
    }

    // @todo: Test.
    if (!is_null($body)) {
      if (!is_string($body)) {
        $body = json_encode($body);
      }
      $options['json'] = $body;
    }

    return $this->client->request($method, $this->endpoint . $path, $options);
  }

}
