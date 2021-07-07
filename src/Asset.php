<?php

namespace Brandfolder;

use Brandfolder\BrandfolderClient;

/**
 * Class representing a Brandfolder asset.
 *
 * @package Brandfolder
 */
class Asset {

  /**
   * Brandfolder API client.
   *
   * @var BrandfolderClient $bf_client
   */
  public $bf_client;

  /**
   * Unique identifier for the Asset.
   *
   * @var string $id
   */
  public $id;

  /**
   * Asset name.
   *
   * @var string $name
   */
  public $name;

  /**
   * Asset description.
   *
   * @var string $description
   */
  public $description;

  /**
   * Asset attachments.
   *
   * @var array $attachments
   */
  public $attachments;

  /**
   * Asset constructor.
   *
   * @param string $asset_id
   * @param \Brandfolder\BrandfolderClient $bf_client
   */
  public function __construct($asset_id, BrandfolderClient $bf_client = NULL) {
    $this->id = $asset_id;

    // @todo
    if (is_null($bf_client)) {
      $bf_client = new BrandfolderClient();
    }
    $this->bf_client = $bf_client;
    
    // @todo: Fetch/create/update BF asset upon instantiation, based on provided data.
  }

  /**
   * Fetches an individual asset.
   *
   * @param array $query_params
   *
   * @return bool|mixed
   *
   * @see https://developers.brandfolder.com/?python#fetch-an-asset
   */
  public function fetch($query_params = []) {
    $result = $this->bf_client->request('GET', "/assets/{$this->id}", $query_params);

    if ($result && isset($result->included)) {
      // Structure the included data as an associative array of items
      // grouped by type and indexed therein by ID.
      $this->bf_client->restructureIncludedData($result);

      // Update the asset to contain useful values for each included
      // attribute rather than just a list of items with IDs.
      $this->decorate($result->data, $result->included);
    }

    return $result;
  }

  /**
   * Create a new asset.
   *
   * @param string $name
   * @param string $description
   * @param array $attachments
   * @param string $section
   * @param string $brandfolder
   * @param string $collection
   *
   * @return bool|string
   *
   * @see https://developer.brandfolder.com/#create-assets
   *
   * @todo: Integrate with constructor.
   */
  public function create($name, $description = NULL, $attachments, $section, $brandfolder = NULL, $collection = NULL) {
    $asset = [
      'name' => $name,
      'attachments' => $attachments,
    ];
    if (!is_null($description)) {
      $asset['description'] = $description;
    }
    $assets = [$asset];

    $result = $this->createMultiple($assets, $section, $brandfolder, $collection);
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
   * @param string $brandfolder
   * @param string $collection
   *
   * @return bool|string
   *
   * @see https://developer.brandfolder.com/#create-assets
   *
   * @todo
   */
  public function createMultiple($assets, $section, $brandfolder = NULL, $collection = NULL) {
    if (!is_null($brandfolder)) {
      $endpoint = "/brandfolders/{$brandfolder}/assets";
    }
    elseif (!is_null($collection)) {
      $endpoint = "/collections/{$collection}/assets";
    }
    if (is_null($endpoint)) {

      return FALSE;
    }

    $body = [
      "data" => [
        "attributes" => $assets,
      ],
      "section_key" => $section,
    ];

    $result = $this->bf_client->request('POST', $endpoint, [], $body);

    return $result;
  }

  /**
   * Update an existing asset.
   *
   * @param null $name
   * @param null $description
   * @param null $attachments
   *
   * @return bool|mixed
   *
   * @see https://developers.brandfolder.com/#update-an-asset
   *
   * @todo: Maintain name/description/attachments as properties of this class...
   */
  public function update($name = NULL, $description = NULL, $attachments = NULL) {
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
    if (count($attributes) == 0) {

      return FALSE;
    }

    $body = [
      "data" => [
        "attributes" => $attributes,
      ]
    ];

    $result = $this->bf_client->request('PUT', "/assets/{$this->id}", [], $body);

    return $result;
  }

  /**
   * Add custom field values to an asset.
   *
   * @param string $asset_id
   * @param array $custom_field_values
   *
   * @return bool|object
   *
   * @see https://developer.brandfolder.com/#create-custom-fields-for-an-asset
   *
   * @todo: Custom fields property, diff on update, etc.
   */
  public function addCustomFieldData($custom_field_values) {
    $attributes = [];
    foreach ($custom_field_values as $key => $value) {
      $attributes[] = [
        'key' => $key,
        'value' => $value,
      ];
    }
    $body = [
      "data" => [
        "attributes" => $attributes,
      ]
    ];
    $result = $this->bf_client->request('POST', "/assets/{$this->id}/custom_fields", [], $body);

    return $result;
  }

  /**
   * Add the asset to a label.
   *
   * @param string $label
   *  The ID/key of the label to which the asset should be added.
   *  @todo: Allow users to provide the human-readable label name if desired.
   *
   * @return bool|object
   *
   * @todo: Add to online documentation?
   */
  public function addToLabel($label) {
    $body = [
      "data" => [
        "asset_keys" => [$this->id],
        "label_key" => $label,
      ]
    ];
    $result = $this->bf_client->request('POST', "/bulk_actions/assets/add_to_label", [], $body);

    return $result;
  }

  /**
   * Delete an asset.
   *
   * @return bool
   *
   * @see https://developers.brandfolder.com/#delete-an-asset
   */
  public function delete($asset_id, $query_params = []) {
    $result = $this->bf_client->request('DELETE', "/assets/{$this->id}");

    return $result != FALSE;
  }

  /**
   * Update an asset to contain useful values for each included
   * attribute rather than just a list of items with IDs.
   *
   * @param $asset
   * @param $included_data
   *
   * @todo
   */
  protected function decorate(&$asset, $included_data) {
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

}
