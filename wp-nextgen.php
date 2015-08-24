<?php
/*
Plugin Name: NextGEN for Lightroom
Description: NextGEN Extension for Lightroom through the WP/LR Sync plugin.
Version: 0.3.0
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
    global $wplr;

    $mapper = C_Gallery_Mapper::get_instance();
		if ( ( $gallery = $mapper->create( array( 'title'	=>	$collection['name'] ) ) ) && $gallery->save() )
			$newGalleryId = $gallery->id();
		else {
      error_log( "Failed to create collection $collectionId." );
      return;
    }
    $wplr->set_meta( 'nextgen_gallery_id', $collectionId, $newGalleryId );

    // Use NextGEN functions to include this collection in a folder
    if ( $inFolderId ) {
      $inAlbumId = $wplr->get_meta( 'nextgen_album_id', $inFolderId );
      $mapper = C_Album_Mapper::get_instance();
      $album = $mapper->find( $inAlbumId );
      $album->sortorder[] = $newGalleryId;
      $mapper->save( $album );
    }
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    global $wplr;

    // Create the entry in NextGEN Album
    $mapper = C_Album_Mapper::get_instance();
		$album = $mapper->create( array( 'name' =>	$folder['name'], 'sortorder' => 'W10=' ) );
		if ( $album->save() )
      $newAlbumId = $album->id();
		else {
      error_log( "Failed to create folder $folderId." );
      return;
    }
    $wplr->set_meta( 'nextgen_album_id', $folderId, $newAlbumId );

    // Use NextGEN functions to include this folder in another folder
    if ( $inFolderId ) {
      $inAlbumId = $wplr->get_meta( 'nextgen_album_id', $inFolderId );
      $mapper = C_Album_Mapper::get_instance();
      $album = $mapper->find( $inAlbumId );
      $album->sortorder[] = "a$newAlbumId";
      $mapper->save( $album );
    }

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
  function move_collection( $collectionId, $inFolderId, $previousFolderId ) {
    $galleryId = $wplr->get_meta( 'nextgen_gallery_id', $collectionId );
    $mapper = C_Album_Mapper::get_instance();

    // Use NextGEN functions to delete this collection from a folder
    if ( $previousFolderId ) {
      $previousAlbumId = $wplr->get_meta( 'nextgen_album_id', $previousFolderId );
      $album = $mapper->find( $previousAlbumId );
      $album->sortorder = array_diff( $album->sortorder, array( $galleryId ) );
      $mapper->save( $album );
    }

    // Use NextGEN functions to include this collection in a folder
    if ( $inFolderId ) {
      $inAlbumId = $wplr->get_meta( 'nextgen_album_id', $inFolderId );
      $album = $mapper->find( $inAlbumId );
      $album->sortorder[] = $galleryId;
      $mapper->save( $album );
    }

  }

  function move_folder( $folderId, $inFolderId, $previousFolderId ) {
    $albumId = $wplr->get_meta( 'nextgen_album_id', $folderId );
    $mapper = C_Album_Mapper::get_instance();

    // Use NextGEN functions to delete this collection from a folder
    if ( $previousFolderId ) {
      $previousAlbumId = $wplr->get_meta( 'nextgen_album_id', $previousFolderId );
      $album = $mapper->find( $previousAlbumId );
      $album->sortorder = array_diff( $album->sortorder, array( "a$albumId" ) );
      $mapper->save( $album );
    }

    // Use NextGEN functions to include this collection in a folder
    if ( $inFolderId ) {
      $inAlbumId = $wplr->get_meta( 'nextgen_album_id', $inFolderId );
      $album = $mapper->find( $inAlbumId );
      $album->sortorder[] = "a$albumId";
      $mapper->save( $album );
    }
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
    global $wplr;
    $id = $wplr->get_meta( "nextgen_gallery_id", $collectionId );
    C_Gallery_Mapper::get_instance()->destroy( $id, TRUE );
    $wplr->delete_meta( "nextgen_gallery_id", $collectionId );
  }

  // Delete the folder.
  function remove_folder( $folderId ) {
    global $wplr;
    $albumId = $wplr->get_meta( 'nextgen_album_id', $folderId );
    C_Album_Mapper::get_instance()->destroy( $albumId, TRUE );
    $wplr->delete_meta( "nextgen_album_id", $folderId );
  }
}

new WPLR_Extension_NextGEN;

?>
