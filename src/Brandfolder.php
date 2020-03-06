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
        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        // If additional data was included in the response (by request),
        // process it to make it more useful.
        // @todo: Assess performance.
        // @todo: Deduplicate code between this method and listAssets().
        if (isset($result->included)) {
          // Structure the included data as an associative array of items
          // grouped by type and indexed therein by ID.
          $this->restructureIncludedData($result);

          // Update the asset to contain useful values for each included
          // attribute rather than just a list of items with IDs.
          $this->decorateAsset($result->data, $result->included);
        }

        return $result;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
  }

  /**
   * Update an existing attachment.
   *
   * @param string $attachment_id
   * @param string $url
   * @param string $filename
   *
   * @return bool|mixed
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/#update-an-attachment
   */
  public function updateAttachment($attachment_id, $url = NULL, $filename = NULL) {
    // @todo: Error handling, centralized.
    try {
      $attributes = [];
      if (!is_null($url)) {
        $attributes['url'] = $url;
      }
      if (!is_null($filename)) {
        $attributes['filename'] = $filename;
      }
      $body = [
        "data" => [
          "attributes" => $attributes,
        ]
      ];
      $response = $this->request('PUT', "/attachments/$attachment_id", [], $body);

      $this->status = $response->getStatusCode();
      if ($this->status == 200) {
        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        return $result;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
  }

  /**
   * Delete an existing attachment.
   *
   * @param string $attachment_id
   *
   * @return bool
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/#update-an-attachment
   */
  public function deleteAttachment($attachment_id) {
    // @todo: Error handling, centralized.
    try {
      $response = $this->request('DELETE', "/attachments/$attachment_id");
      $this->status = $response->getStatusCode();
      if ($this->status == 200) {
        return TRUE;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }

    return TRUE;
  }

    /**
   * Deletes an asset.
   *
   * @param $asset_id
   *
   * @return bool
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/#delete-an-asset
   */
  public function deleteAsset($asset_id, $query_params = []) {
    // @todo: Error handling, centralized.
    try {
      $response = $this->request('DELETE', "/assets/$asset_id");
      $this->status = $response->getStatusCode();

      if ($this->status == 200) {
        return TRUE;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
    return TRUE;
  }

  /**
   * Update an existing asset.
   *
   * @param string $asset_id
   * @param null $name
   * @param null $description
   * @param null $attachments
   *
   * @return bool|mixed
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/#update-an-asset
   */
  public function updateAsset($asset_id, $name = NULL, $description = NULL, $attachments = NULL) {
    // @todo: Error handling, centralized.
    try {
      $attributes = [];
      if (!is_null($name)) {
        $attributes['name'] = $name;
      }
      if (!is_null($description)) {
        $attributes['description'] = $description;
      }
      if (!is_null($attachments)) {
        $attributes['attachments'] = $attachments;
      }
      $body = [
        "data" => [
          "attributes" => $attributes,
        ]
      ];
      $response = $this->request('PUT', "/assets/$asset_id", [], $body);

      $this->status = $response->getStatusCode();
      if ($this->status == 200) {
        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        return $result;
      }
    }
    catch (ClientException $e) {
      $this->status = $e->getCode();
      $this->message = $e->getMessage();

      return FALSE;
    }
  }

  /**
   * Lists multiple assets.
   *
   * @param array $query_params
   * @param string|null $collection
   *  An ID of a collection within which to search for assets, or "all" to look
   *  throughout the entire Brandfolder. If this param is null, the operation
   *  will use the previously defined default collection, if applicable.
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
        if ($collection == 'all' || is_null($collection)) {
          $endpoint = "/brandfolders/{$this->default_brandfolder_id}/assets";
        }
        else {
          $endpoint = "/collections/$collection/assets";
        }
        $response = $this->request('GET', $endpoint, $query_params);

        $this->status = $response->getStatusCode();
        if ($this->status == 200) {
          $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

          // If additional data was included in the response (by request),
          // process it to make it more useful.
          // @todo: Assess performance.
          if (isset($result->included)) {
            // Structure the included data as an associative array of items
            // grouped by type and indexed therein by ID.
            $this->restructureIncludedData($result);

            // Update each asset to contain useful values for each included
            // attribute rather than just a list of items with IDs.
            // @todo: Make decorateAsset method more generic so it can handle data returned from any API request that supports the "include" param.
            array_walk($result->data, function($asset) use ($result) {
              $this->decorateAsset($asset, $result->included);
            });
          }

          return $result;
        }
        else {
          // @todo.
          return FALSE;
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
   * Structure included data as an associative array of items grouped by
   * type and indexed therein by ID.
   *
   * @param $result
   */
  protected function restructureIncludedData(&$result) {
    $included = [];
    foreach ($result->included as $item) {
      $included[$item->type][$item->id] = $item->attributes;
    }
    $result->included = $included;
  }

  /**
   * Update an asset to contain useful values for each included
   * attribute rather than just a list of items with IDs.
   *
   * @param $asset
   * @param $included_data
   */
  protected function decorateAsset(&$asset, $included_data) {
    foreach ($asset->relationships as $type_label => $data) {
      // Data here will either be an array of objects or a single object.
      // In the latter case, wrap in an array for consistency.
      $items = is_array($data->data) ? $data->data : [$data->data];
      foreach ($items as $item) {
        $type = $item->type;
        if (isset($included_data[$type][$item->id])) {
          $attributes = $included_data[$type][$item->id];
          // For custom field values, set up a convenient array keyed
          // by field keys and containing field values. If users
          // need to know the unique ID of a particular custom field
          // instance, they can still look in $asset->relationships.
          if ($type == 'custom_field_values') {
            $key = $attributes->key;
            $asset->{$type}[$key] = $attributes->value;
          }
          else {
            $attributes->id = $item->id;
            $asset->{$type}[$item->id] = $attributes;
          }
        }
      }
    }

    // Sort attachments by position. Retain the useful ID keys.
    if (isset($asset->attachments) && count($asset->attachments) > 1) {
      $ordered_attachments = [];
      $ordered_attachment_ids = [];
      foreach ($asset->attachments as $attachment) {
        $ordered_attachments[$attachment->position] = $attachment;
        $ordered_attachment_ids[$attachment->position] = $attachment->id;
      }
      ksort($ordered_attachments);
      ksort($ordered_attachment_ids);
      $asset->attachments = array_combine($ordered_attachment_ids, $ordered_attachments);
    }
  }

  /**
   * Retrieves tags used in a Brandfolder.
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
  public function getTags($query_params = []) {
    // @todo: Error handling, centralized.
    try {
      if (isset($this->default_brandfolder_id)) {
        $endpoint = "/brandfolders/{$this->default_brandfolder_id}/tags";
        $response = $this->request('GET', $endpoint, $query_params);

        $this->status = $response->getStatusCode();
        if ($this->status == 200) {
          $data = \GuzzleHttp\json_decode($response->getBody()->getContents());

          // @todo: Don't just return ->data. Also return ->meta or come up with some other way to let consumers use it. It's important for pagination, etc.
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
   * Lists Invitations to an Organization, Brandfolder, or Collection.
   *
   * @param array $query_params
   * @param string|null $organization
   * @param string|null $brandfolder
   * @param string|null $collection
   *
   * @return bool|mixed
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-invitations
   */
  public function listInvitations($query_params = [], $organization = NULL, $brandfolder = NULL, $collection = NULL) {
    // @todo: Error handling, centralized.
    try {
      if (!is_null($organization)) {
        // @todo: Store default organization.
        $endpoint = "/organizations/$organization/invitations";
      }
      else {
        if (is_null($brandfolder, $collection)) {
          if (!is_null($this->default_brandfolder_id)) {
            $brandfolder = $this->default_brandfolder_id;
          }
          elseif (!is_null($this->default_collection_id)) {
            $collection = $this->default_collection_id;
          }
        }
        if (!is_null($brandfolder)) {
          $endpoint = "/brandfolders/{$this->default_brandfolder_id}/invitations";
        }
        elseif (!is_null($collection)) {
          $endpoint = "/collections/{$this->default_collection_id}/invitations";
        }
      }
      if (isset($endpoint)) {
        $response = $this->request('GET', $endpoint, $query_params);

        $this->status = $response->getStatusCode();
        if ($this->status == 200) {
          $result = \GuzzleHttp\json_decode($response->getBody()
            ->getContents());

          // If additional data was included in the response (by request),
          // process it to make it more useful.
          // @todo: Assess performance.
          if (isset($result->included)) {
            // Structure the included data as an associative array of items
            // grouped by type and indexed therein by ID.
            // @todo: Test.
            $this->restructureIncludedData($result);

            // Update each asset to contain useful values for each included
            // attribute rather than just a list of items with IDs.
            // @todo: Make decorateAsset method more generic so it can handle data returned from any API request that supports the "include" param.
//            array_walk($result->data, function ($asset) use ($result) {
//              $this->decorateAsset($asset, $result->included);
//            });
          }

          return $result;
        }
        else {
          // @todo.
          return FALSE;
        }
      }
      else {

        return FALSE;
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

    if (!is_null($body)) {
      $options['json'] = $body;
    }

    return $this->client->request($method, $this->endpoint . $path, $options);
  }

}
