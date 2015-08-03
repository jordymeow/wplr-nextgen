<?php

/*
Plugin Name: NextGen for Lightroom
Description: NextGen Extension for WP/LR Sync.
Version: 0.1.0
Author: Jordy Meow
Author URI: http://www.meow.fr
*/

class WPLR_Extension_NextGen {

  public function __construct() {

    // Reset
    add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );

    // Move
    add_action( "wplr_move_folder", array( $this, 'move_collection' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );
    add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
  }

  function log( $data ) {
    $fh = fopen( trailingslashit( plugin_dir_path( __FILE__ ) ) . '/logs.log', 'a' );
  	$date = date( "Y-m-d H:i:s" );
  	fwrite( $fh, "$date: {$data}\n" );
  	fclose( $fh );
  }

  // Plugins are asked to reset their support of folders/collections and the media in them.
  // This is triggered by the Reset button in the Settings -> Media -> WP/LR Sync -> Maintenance.
  function reset() {
    $this->log( "reset" );
  }

  // Created a new collection (ID $collectionId).
  // Placed in the folder $inFolderId, or in the root if empty.
  function create_collection( $collectionId, $inFolderId, $collection ) {
    if ( empty( $inFolderId ) )
      $inFolderId = "root";
    $this->log( sprintf( "create_collection %d (%s) in %s.", $collectionId, $collection['name'], $inFolderId ), true );
  }

  // Created a new folder (ID $folderId).
  // Placed in the folder $inFolderId, or in the root if empty.
  function create_folder( $folderId, $inFolderId, $folder ) {
    if ( empty( $inFolderId ) )
      $inFolderId = "root";
    $this->log( sprintf( "create_folder %d (%s) in %s.", $folderId, $folder['name'], $inFolderId ), true );
  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    $this->log( sprintf( "update_collection %d (%s).", $collectionId, $collection['name'] ), true );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    $this->log( sprintf( "update_folder %d (%s).", $folderId, $folder['name'] ), true );
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    if ( empty( $folderId ) )
      $folderId = "root";
    if ( empty( $previousFolderId ) )
      $previousFolderId = "root";
    $this->log( sprintf( "move_collection %d from %s to %s.", $collectionId, $previousFolderId, $folderId ), true );
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId ) {
    $this->log( sprintf( "add_media_to_collection %d to collection %d.", $mediaId, $collectionId ), true );
  }

  // Remove media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    $this->log( sprintf( "remove_media_from_collection %d from %d.", $mediaId, $collectionId ), true );
  }

  // The media was physically deleted.
  function remove_media( $mediaId ) {
    $this->log( sprintf( "remove_media %d.", $mediaId ), true );
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    $this->log( sprintf( "remove_collection %d.", $collectionId ), true );
  }

}

new WPLR_Extension_NextGen;

?>
