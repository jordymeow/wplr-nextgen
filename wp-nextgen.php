<?php
/*
Plugin Name: NextGEN for Lightroom
Description: NextGEN Extension for Lightroom through the WP/LR Sync plugin.
Version: 0.1.0
Author: Jordy Meow
Author URI: http://www.meow.fr
*/

class WPLR_Extension_NextGEN {

  public function __construct() {

    // Init
    add_filter( 'wplr_extensions', array( $this, 'extensions' ), 10, 1 );

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );
    add_action( "wplr_move_folder", array( $this, 'move_folder' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Delete
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
    add_action( "wplr_remove_folder", array( $this, 'remove_folder' ), 10, 1 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );
    add_action( "wplr_update_media", array( $this, 'update_media' ), 10, 2 );

    // Extra
    //add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );
    //add_action( "wplr_clean", array( $this, 'clean' ), 10, 1 );
    //add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
  }

  function extensions( $extensions ) {
    array_push( $extensions, 'NextGEN' );
    return $extensions;
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {
    global $wpdb, $wplr;
    $ngg_album = $wpdb->prefix . "ngg_gallery";
    $wpdb->insert( $ngg_album,
      array(
        'name' => $collection['name'],
        'title' => $collection['name'],
        'slug' => sanitize_title( $collection['name'] ),
        'author' => get_current_user_id(),
        'path' => 'wp-content\gallery\\' . sanitize_title( $collection['name'] ),
        'galdesc' => ''
      )
    );
    $wplr->set_meta( 'nextgen_gallery_id', $collectionId, $wpdb->insert_id );
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    global $wpdb, $wplr;
    $ngg_album = $wpdb->prefix . "ngg_album";

    // Create the entry in NextGEN Album
    $wpdb->insert( $ngg_album,
      array(
        'name' => $folder['name'],
        'sortorder' => 'W10=',
        'slug' => sanitize_title( $folder['name'] )
      )
    );
    $wplr->set_meta( 'nextgen_album_id', $folderId, $wpdb->insert_id );

    // Create the ngg_album post type entry (mixin_nextgen_table_extras)
    // No idea why NextGEN is doing that to store its metadata, there are definitely better ways.
    $post = array(
      'post_title'    => 'Untitled ngg_album',
      'post_name'     => 'mixin_nextgen_table_extras',
      'post_status'   => 'draft',
      'post_type'     => 'ngg_album'
    );
    $id = wp_insert_post( $post );
    $wplr->set_meta( 'nextgen_post_album_id', $folderId, $id );

  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    $wplr->get_meta( 'nextgen_album_id', $folderId );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId ) {
    global $wplr;

    // Upload the file to the gallery
    $gallery_id = $wplr->get_meta( 'nextgen_gallery_id', $collectionId );
    $abspath = get_attached_file( $mediaId );
    $file_data = file_get_contents( $abspath );
    $file_name = M_I18n::mb_basename( $abspath );
    $attachment = get_post( $mediaId );
    $storage = C_Gallery_Storage::get_instance();
    $image = $storage->upload_base64_image( $gallery_id, $file_data, $file_name );
    $wplr->set_meta( 'nextgen_collection_' . $collectionId . '_image_id', $mediaId, $image->id() );

    // Import metadata from WordPress
    $image_mapper = C_Image_Mapper::get_instance();
    $image = $image_mapper->find( $image->id() );
    if ( !empty( $attachment->post_excerpt ) )
      $image->alttext = $attachment->post_excerpt;
    if ( !empty( $attachment->post_content ) )
      $image->description = $attachment->post_content;
    $image_mapper->save( $image );
  }

  // Remove media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    global $wplr;
    $imageId = $wplr->get_meta( 'nextgen_collection_' . $collectionId . '_image_id', $mediaId );
    $image_mapper = C_Image_Mapper::get_instance();
    $image = $image_mapper->find( $imageId );
    $storage = C_Gallery_Storage::get_instance();
    $storage->delete_image( $image );
    $wplr->delete_meta( 'nextgen_collection_' . $collectionId . '_image_id', $mediaId );
  }

  // The media file was updated.
  // Since NextGEN uses its own copies, we need to delete the current one and add a new one.
  function update_media( $mediaId, $collectionIds ) {
    foreach ( $collectionIds as $collectionId ) {
      $this->remove_media_from_collection( $mediaId, $collectionId );
      $this->add_media_to_collection( $mediaId, $collectionId );
    }
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    global $wpdb, $wplr;
    $id = $wplr->get_meta( "nextgen_gallery_id", $collectionId );
    $ngg_gallery = $wpdb->prefix . "ngg_gallery";
    $wpdb->delete( $ngg_gallery, array( 'gid' => $id ) );
    $wplr->delete_meta( "nextgen_gallery_id", $collectionId );
  }

  // Delete the folder.
  function remove_folder( $folderId ) {
    global $wpdb, $wplr;

    // Delete the album
    $ngg_album = $wpdb->prefix . "ngg_album";
    $albumId = $wplr->get_meta( 'nextgen_album_id', $folderId );
    $wpdb->delete( $albumId, array( 'id' => $albumId ) );

    // Delete post and meta related to that album
    $postId = $wplr->get_meta( 'nextgen_post_album_id', $folderId );
    wp_delete_post( $postId, true );
  }
}

new WPLR_Extension_NextGEN;

?>
