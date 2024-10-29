<?php
/*
Plugin Name: Autopaginate
Plugin URI: http://simonwheatley.co.uk/wordpress/autopaginate
Description: Provide the ability to automatically paginate your posts by scattering the <code>&lt;--!nextpage--&gt;</code> quicktag throughout.
Author: Simon Wheatley
Version: 1.0
Author URI: http://simonwheatley.co.uk/wordpress/
*/

/*  Copyright 2009 Simon Wheatley

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once( dirname (__FILE__) . '/class-Autopaginate-Plugin.php' );

/**
 *
 * @package default
 * @author Simon Wheatley
 **/
class Autopaginate extends Autopaginate_Plugin
{

	/**
	 * The preferred number of words per page, set from an option
	 *
	 * @var string
	 **/
	protected $pref_words_per_page;

	protected $paginate;

	public function __construct() {
		if ( ! is_admin() )
			return;
		// Activate
		$this->register_activation ( __FILE__ );
		// Admin init
		$this->add_action( 'admin_init' );
		// Register and stuff
		$this->register_plugin ( 'autopaginate', __FILE__ );
		// The guts
		$this->add_action( 'transition_post_status', null, null, 3 );
		$this->add_action( 'save_post', null, null, 2 );
		$this->add_action( 'post_submitbox_start', 'force_repagination_ctrl' );

		$this->pref_words_per_page = (int) get_option( 'ap_pref_words_per_page' );
		$this->paginate = false;
	}
	
	// HOOKS
	// -----
	
	public function activate() {
		// Default settings
		$this->set_initial_default_option( 'ap_pref_words_per_page', 700 );
	}
		
	public function admin_init() {
		add_settings_field( 'ap_pref_words_per_page', __( 'Preferred words per page', 'autopaginate' ), array( & $this, 'pref_words_per_page' ), 'writing' );
		register_setting( 'writing', 'ap_pref_words_per_page', 'intval' );
	}
	
	public function pref_words_per_page() {
		$vars = array();
		$vars[ 'words' ] = $this->pref_words_per_page;
		$this->render_admin( 'pref-words-per-page', $vars );
	}
	
	public function force_repagination_ctrl() {
		// Render template
		$this->render_admin( 'force-repagination-ctrl', array() );
	}
	
	// If the new status is "publish", and the old status wasn't "publish", then autopaginate (if it's not already paginated)
	public function transition_post_status( $new_status, $old_status, $post ) {
		// If this post ISN'T moving to published from some un-published state then stop right now
		if ( ! $this->is_being_published( $new_status, $old_status ) ) 
			return;
		// If we're already paginated, and we're not forcing it or we wouldn't be here in the code, then stop right now
		if ( $this->is_already_paginated( $post ) ) 
			return;
		// Otherwise set the flag to paginate later
		$this->paginate = true;
	}
	
	public function save_post( $post_id, $post ) {
		// If we're forcing the issue, set the flag to paginate
		if ( $this->force_repagination() ) 
			$this->paginate = true;
		// Now, maybe, do the pagination
		if ( $this->paginate ) 
			$this->do_pagination( $post );
	}
	
	// UTILITIES
	// ---------
	
	protected function do_pagination( $post ) {
		// OK. The post has already been saved to the DB so we've little danger of being overwritten.
		// Now we change the content to be paginated
		$post = & $this->remove_pagination( $post );
		$post = & $this->add_pagination( $post );
		$this->save_post_content( $post );
	}
	
	protected function remove_pagination( $post ) {
		$post->post_content = str_replace( '<!--nextpage-->', '', $post->post_content );
		return $post;
	}

	protected function add_pagination( $post ) {
		// First convert any paragraph tags to linebreaks
		$content = $this->para_to_linebreaks( $post->post_content );
		// Split into paragraphs
		$paras = explode( "\n\n", $content );
		// Process the paras, and add page breaks
		$word_count = 0;
		$num_paras = count( $paras );
		foreach( $paras as $para_num => & $para ) {
			// Don't add a next page tag at the end of the last para
			if ( ( $para_num + 1 ) == $num_paras ) continue;
			// Explode those paragraphs into words
			preg_match_all("/\S+/", $para, $matches);
			$para = $matches[ 0 ];
			// Count the words and page break where appropriate
			$word_count += count( $para );
			if ( $word_count > $this->pref_words_per_page ) {
				$word_count = 0;
				$para[ count( $para ) - 1 ] .= '<!--nextpage-->';
			}
			// Reassemble para
			$para = implode( ' ', $para );
		}
		// Reassemble paras as content
		$post->post_content = implode( "\n\n", $paras );
		// Because we have a reference to $post, we don't need to return
		return $post;
	}
	
	protected function save_post_content( $post ) {
		global $wpdb;
		$data = array( 'post_content' => $post->post_content );	
		$post_id = $post->ID;
		if ( $post->post_type == 'revision' ) 
			$post_id = $post->post_parent;
		$where = array( 'ID' => $post_id );
		$wpdb->update( $wpdb->posts, $data, $where );
	}
	
	protected function para_to_linebreaks( $content ) {
		// Cross platform newlines
		$content = str_replace(array("\r\n", "\r"), "\n", $content);
		// Get rid of closing P elements
		$content = str_replace( '</p>', '', $content );
		// Convert opening P elements to double linebreaks
		// N.B. This doesn't cope with P elements with attributes
		$content = str_replace( '<p>', "\n\n", $content );
		// All done
		return $content;
	}
	
	protected function is_already_paginated( $post ) {
		return ( strpos( $post->post_content, '<!--nextpage-->' ) );
	}
	
	protected function is_being_published( $new_status, $old_status ) {
		return ( $new_status == 'publish' && $old_status != 'publish' );
	}
	
	protected function force_repagination() {
		// Do we have anything to do?
		$force_repagination = (bool) @ $_POST[ 'ap_force_repagination' ];
		if ( ! $force_repagination ) 
			return false;
		// Are we authorised to do anything?
		$this->verify_nonce( 'ap_autopagination' );
		return true;

	}

	protected function verify_nonce( $action ) {
		$nonce = @ $_POST[ '_ap_nonce' ];
		if ( wp_verify_nonce( $nonce, $action ) ) 
			return true;
//		throw new exception( "Wrong wrong wrong $action, $nonce" );
		wp_die( __('Sorry, there has been an error. Please hit the back button, refresh the page and try again.') );
		exit; // Redundant, unless wp_die fails.
	}
	
	// Set a default if there's no value for the option, otherwise effectively do nothing
	protected function set_initial_default_option( $name, $value ) {
		// Get the option with a default if it doesn't exist
		$value = get_option( $name, $value );
		// If it doesn't then set the option value
		update_option( $name, $value );
	}

}

/**
 * Instantiate the plugin
 *
 * @global
 **/

$autopaginate = new Autopaginate();

?>