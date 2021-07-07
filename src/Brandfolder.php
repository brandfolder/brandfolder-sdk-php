<?php

namespace Brandfolder;

use Brandfolder\BrandfolderClient;

/**
 * Represents an individual "Brandfolder."
 *
 * @package Brandfolder
 */
class Brandfolder {

  /**
   * Brandfolder API client.
   *
   * @var BrandfolderClient $bf_client
   */
  public $bf_client;

  /**
   * Unique identifier for the Brandfolder.
   *
   * @var string $id
   */
  public $id;

  /**
   * @todo
   *
   * The Brandfolder's human-readable name.
   *
   * @var string $name
   */
  public $name;

  /**
   * @todo
   *
   * The Brandfolder's slug (used in its URL).
   *
   * @var string $slug
   */
  public $slug;

  /**
   * Brandfolder constructor.
   *
   * @param string $brandfolder_id
   * @param BrandfolderClient $BfClient
   *
   * @todo: Create new Brandfolder if ID is null.
   */
  public function __construct(string $brandfolder_id, BrandfolderClient $brandfolder_client) {
    $this->api_key = $api_key;
    $this->bf_client = $brandfolder_client;
  }

  /**
   * Gets Collections belonging this Brandfolder.
   *
   * @param array $query_params
   * @return bool|array
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see https://developers.brandfolder.com/?http#list-collections
   */
  public function getCollections($query_params = []) {
    if ($result = $this->bf_client->request('GET', "/brandfolders/{$this->id}/collections", $query_params)) {
      $collections = [];
      if (isset($result->data)) {
        foreach ($result->data as $collection_data) {
          $collections[$collection_data->id] = $collection_data->attributes->name;
        }
      }

      return $collections;
    }
    else {

      return FALSE;
    }
  }

  /**
   * Create a new asset.
   *
   * @param string $name
   * @param string $description
   * @param array $attachments
   * @param string $section
   *
   * @return bool|mixed
   *
   * @see https://developer.brandfolder.com/#create-assets
   */
  public function createAsset($name, $description = NULL, $attachments, $section) {
    $asset = [
      'name' => $name,
      'attachments' => $attachments,
    ];
    if (!is_null($description)) {
      $asset['description'] = $description;
    }
    $assets = [$asset];

    $result = $this->createAssets($assets, $section);
    if ($result && is_array($result->data)) {

      return $result->data[0];
    }

    return FALSE;
  }

  /**
   * Create multiple new assets in one operation.
   *
   * @param array $assets
   *  Array consisting of:
   *    'name' (string)
   *    'description' (optional string)
   *    'attachments' (array)
   * @param string $section
   *
   * @return bool|mixed
   *
   * @see https://developer.brandfolder.com/#create-assets
   */
  public function createAssets($assets, $section) {
    $endpoint = "/brandfolders/{$this->id}/assets";

    $body = [
      "data"        => [
        "attributes" => $assets,
      ],
      "section_key" => $section,
    ];

    if ($result = $this->bf_client->request('POST', $endpoint, [], $body)) {

      return $result;
    }
    else {

      return FALSE;
    }
  }

  /**
   * List multiple assets that belong to the Brandfolder.
   *
   * @param array $query_params
   *
   * @return bool|mixed
   *
   * @see https://developers.brandfolder.com/?http#list-assets
   */
  public function listAssets($query_params = []) {
    $endpoint = "/brandfolders/{$this->id}/assets";
    if ($result = $this->bf_client->request('GET', $endpoint, $query_params)) {
      // If additional data was included in the response (by request),
      // process it to make it more useful.
      // @todo: Assess performance.
      if (isset($result->included)) {
        // Structure the included data as an associative array of items
        // grouped by type and indexed therein by ID.
        $this->bf_client->restructureIncludedData($result);

        // @todo: Use Asset class.

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

      return FALSE;
    }
  }

  /**
   * @todo
   *
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
   * @todo
   *
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
   * @todo
   *
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

}
