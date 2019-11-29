<?php

namespace Brandfolder;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
//use GuzzleHttp\Exception\RequestException;
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
   */
  private $default_brandfolder_id;

  /**
   * Brandfolder constructor.
   *
   * @param string $api_key
   * @param \GuzzleHttp\ClientInterface|NULL $client
   */
  public function __construct($api_key, ClientInterface $client = NULL) {
    $this->api_key = $api_key;

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
   * @return ResponseInterface
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-brandfolders
   */
  public function getBrandfolders($query_params = []) {
    return $this->request('GET', '/brandfolders', $query_params);
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
   * @return ResponseInterface
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

    return $this->request('GET', "/brandfolders/{$brandfolder_id}/collections", $query_params);
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
  public function request($method, $path, $query_params = [], $body = []) {

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->api_key,
      ],
    ];

    // @todo: params

    // @todo: body

    return $this->client->request($method, $this->endpoint . $path, $options);
  }

}
