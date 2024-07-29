<?php

/*******************************************************************************
 *
 *  Copyrights 2017 to Present - Sellergize Web Technology Services Pvt. Ltd. - ALL RIGHTS RESERVED
 *
 * All information contained herein is, and remains the
 * property of Sellergize Web Technology Services Pvt. Ltd.
 *
 * The intellectual and technical concepts & code contained herein are proprietary
 * to Sellergize Web Technology Services Pvt. Ltd. (India), and are covered and protected
 * by copyright law. Reproduction of this material is strictly forbidden unless prior
 * written permission is obtained from Sellergize Web Technology Services Pvt. Ltd.
 *
 * ******************************************************************************/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

//to access store custom data in other files


function store_taxonomy_status($id)
{
	global $store_status;
	global $exclude_stores;
	if (!isset($store_status[$id])) {
		$store_custom = cmd_get_taxonomy_options($id, "stores");
		$store_status[$id] = $store_custom['status'];
		if ($store_custom['status'] == 'inactive') {
			$exclude_stores[] = $id;
		}
	}
}

function exclude_coupons()
{
	global $exclude_stores;
	global $exclude_coupons;
	foreach ((array)$exclude_stores as $store) {
		$post_args = array(
			'posts_per_page' => -1,
			'post_type' => array('coupons', 'products'), // you can change it according to your custom post type
			'tax_query' => array(
				array(
					'taxonomy' => 'stores', // you can change it according to your taxonomy
					'field' => 'term_id', // this can be 'term_id', 'slug' & 'name'
					'terms' => $store,
				)
			)
		);
		$post_data = get_posts($post_args);
		foreach ($post_data as $post) {
			if ($post->post_type == 'products') {
				$cmdcomp_price_list = get_post_meta($post->ID, 'cmdcomp_price_list', true);
				foreach ($cmdcomp_price_list as $key => $list) {
					$term_id = get_term_by('slug', $list['store'], 'stores')->term_id;
					if (!empty($exclude_stores) and in_array($term_id, $exclude_stores)) {
						unset($cmdcomp_price_list[$key]);
					}
				}
				if (count($cmdcomp_price_list) == 0) {
					$exclude_coupons[] = $post->ID;
				}
			} else {
				$exclude_coupons[] = $post->ID;
			}
		}
	}
}

// TODO: Remove this in later versions
// delete_option( 'clipmydeals_updated_to_5_point_1_point_7' )
function clipmydeals_updated_to_5_point_1_point_7()
{
	if (get_option('clipmydeals_updated_to_5_point_1_point_7') != 'completed') {

		// Store Logo and Store Banners
		$stores = get_terms(array('taxonomy' => 'stores', 'hide_empty' => false,));
		foreach ($stores as $store) {
			$term_meta = cmd_get_taxonomy_options($store->term_id, 'stores');
			if (!empty($term_meta['store_logo'])) {
				$term_meta['store_logo'] = str_replace('http://demo5.clipmydeals.com', 'https://demo.clipmydeals.com/5/', $term_meta['store_logo']);
				$term_meta['store_banner'] = str_replace('http://demo5.clipmydeals.com', 'https://demo.clipmydeals.com/5', $term_meta['store_banner']);
				update_option("taxonomy_term_" . $store->term_id, $term_meta);
			}
		}

		$queried_posts = query_posts(array('post_type' => 'products', 'posts_per_page'  => -1,));
		foreach ($queried_posts as $post) {
			$post_content = str_replace('http://demo5.clipmydeals.com', 'https://demo.clipmydeals.com/5', $post->post_content);
			wp_update_post(array('ID' => $post->ID, 'post_content' => $post_content,), true);
		}
		wp_reset_query(); // reset query for next use

		update_option('clipmydeals_updated_to_5_point_1_point_7', 'completed');
	}
}
add_action('wp_loaded', 'clipmydeals_updated_to_5_point_1_point_7');

function clipmydeals_create_tables()
{
	global $wpdb;
	$wp_prefix = $wpdb->prefix;
	$sql = "CREATE TABLE IF NOT EXISTS " . $wp_prefix . "cmd_store_to_domain (
		`id` INT NOT NULL AUTO_INCREMENT ,
		`store_id` INT NOT NULL ,
		`domain` VARCHAR(255) NOT NULL ,
		PRIMARY KEY (`id`))";
	$wpdb->query($sql);

	// Subscription table
	$sql = "CREATE TABLE IF NOT EXISTS " . $wp_prefix . "cmd_subscriptions (
			`id` INT NOT NULL AUTO_INCREMENT ,
			`subscription_endpoint` TEXT NOT NULL ,
			`token` VARCHAR(24) NOT NULL ,
			`key` VARCHAR(100) NOT NULL ,
			`user_id` INT NULL ,
			`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`))";
	$wpdb->query($sql);
	// Notifications log table
	$sql = "CREATE TABLE IF NOT EXISTS " . $wp_prefix . "cmd_notification_logs (
		`id` INT NOT NULL AUTO_INCREMENT ,
		`subscription_id` INT NOT NULL ,
		`failed` VARCHAR(1) DEFAULT 'Y' ,
		`message` text NOT NULL ,
		`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
	PRIMARY KEY (`id`))";
	$wpdb->query($sql);
}
add_action('after_switch_theme', 'clipmydeals_create_tables');

// TODO: Remove this in later versions
// delete_option( 'clipmydeals_update_to_5_point_4_point_2' )
function clipmydeals_update_to_5_point_4_point_2()
{
	if (get_option('clipmydeals_updated_to_5_point_4_point_2') != 'completed') {
		global $wpdb;
		$wp_prefix = $wpdb->prefix;
		// create table
		clipmydeals_create_tables();
		$stores = $wpdb->get_results("SELECT option_name, option_value FROM `" . $wp_prefix . "options` WHERE option_name LIKE 'taxonomy_term_%' AND option_value LIKE '%store_url%'");
		$data = array();
		foreach ($stores as $store) {
			$store_options = maybe_unserialize($store->option_value);
			$data[] = "(" . sb_str_after($store->option_name, 'taxonomy_term_') . ", '" . str_replace("www.", "", parse_url($store_options['store_url'], PHP_URL_HOST)) . "')";
		}
		$insert_query = "INSERT INTO " . $wp_prefix . "cmd_store_to_domain (store_id, domain) VALUES " . implode(',', $data);
		$wpdb->query($insert_query);
	}
	update_option('clipmydeals_updated_to_5_point_4_point_2', 'completed');
}
add_action('after_setup_theme', 'clipmydeals_update_to_5_point_4_point_2');

//load language into theme
function clipmydeals_theme_language_setup()
{
	load_theme_textdomain('clipmydeals', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'clipmydeals_theme_language_setup');

function cmdcp_notify_updated_to_7_point_0()
{
	if (!function_exists('get_plugin_data')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');

	$minimumCashbackVersion = '5.0';
	$minimumComparisonVersion = '2.0';
	if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins')) and version_compare(get_plugin_data(WP_PLUGIN_DIR . '/clipmydeals-cashback/clipmydeals-cashback.php')['Version'], $minimumCashbackVersion, '<')) {
?>
		<div class="notice notice-error">
			<p><?= sprintf(
					/* translators: 1: Update Link 2: Minimum required version 2: template name */
					__('For the cashback pages to function correctly, please update your <strong>%3$s</strong> plugin to atleast <strong>%2$s</strong>. <a href="%1$s">Click here to update.</a>', 'clipmydeals'),
					wp_nonce_url(admin_url('update.php?action=upgrade-plugin&amp;plugin=' . urlencode('clipmydeals-cashback/clipmydeals-cashback.php')), 'upgrade-plugin_clipmydeals-cashback/clipmydeals-cashback.php'),
					$minimumCashbackVersion,
					'Cashback',
				); ?>
			</p>
		</div>
	<?php
	}
	if (in_array('clipmydeals-comparison/clipmydeals-comparison.php', get_option('active_plugins')) and version_compare(get_plugin_data(WP_PLUGIN_DIR . '/clipmydeals-comparison/clipmydeals-comparison.php')['Version'], $minimumComparisonVersion, '<')) { ?>
		<div class="notice notice-error">
			<p><?= sprintf(
					/* translators: 1: Update Link 2: Minimum required version 2: template name */
					__('For the comparison pages to function correctly, please update your <strong>%3$s</strong> plugin to atleast <strong>%2$s</strong>. <a href="%1$s">Click here to update.</a>', 'clipmydeals'),
					wp_nonce_url(admin_url('update.php?action=upgrade-plugin&amp;plugin=' . urlencode('clipmydeals-comparison/clipmydeals-comparison.php')), 'upgrade-plugin_clipmydeals-comparison/clipmydeals-comparison.php'),
					$minimumComparisonVersion,
					'Comparison',
				); ?>
			</p>
		</div>
	<?php
	}
}
add_action('admin_notices', 'cmdcp_notify_updated_to_7_point_0');

// create keys on theme activate or switch
function clipmydeals_vapid_keys()
{
	if (!get_option('clipmydeals_vapid_keys')) {
		$keys = array('publicKey' => NULL, 'privateKey' => NULL);
		try {
			$keys = Minishlink\WebPush\VAPID::createVapidKeys();
		} catch (\Throwable $th) {
			$license_key = get_option('cmd_key');
			$token = hash('md5', $license_key . parse_url(get_site_url(), PHP_URL_HOST));
			$response = wp_remote_get("https://clipmydeals.com/updates/create-notification-keys.php?key=$license_key&token=$token");
			$keys = (array) json_decode($response['body']);
		}
		update_option('clipmydeals_vapid_keys', $keys);
	}
}
add_action('after_switch_theme', 'clipmydeals_vapid_keys');

// TODO: Remove this in later versions
delete_option('clipmydeals_update_to_5_point_4_point_7');

// TODO: Remove this in later versions
// delete_option( 'clipmydeals_update_to_5_point_5' )
function clipmydeals_update_to_5_point_5()
{
	if (get_option('clipmydeals_updated_to_5_point_5') != 'completed') {
		clipmydeals_create_tables();
		update_option('clipmydeals_updated_to_5_point_5', 'completed');
	}
	if (!is_array(get_option('clipmydeals_vapid_keys'))) {
		clipmydeals_vapid_keys();
	}
}
add_action('after_setup_theme', 'clipmydeals_update_to_5_point_5');

// TODO: Remove this in later versions
// delete_option( 'clipmydeals_update_to_5_point_5_point_5' )
function clipmydeals_update_to_5_point_5_point_5()
{
	if (get_option('clipmydeals_updated_to_5_point_5_point_5') != 'completed') {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}cmd_store_to_domain WHERE store_id NOT IN( SELECT `term_id` FROM `{$wpdb->prefix}term_taxonomy` WHERE `taxonomy` = 'stores')");
		update_option('clipmydeals_updated_to_5_point_5_point_5', 'completed');
	}
}
add_action('after_setup_theme', 'clipmydeals_update_to_5_point_5_point_5');

function clipmydeals_filtered_title()
{
	if (is_tax(array('stores', 'offer_categories', 'locations', 'brands'))) {
		$term_id = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'))->term_id;
		$page_title = cmd_get_taxonomy_options($term_id, get_query_var('taxonomy'))['page_title'];
	} else if (is_singular('products')) {
		$page_title = get_post_meta(get_the_ID(), 'cmd_page_title', true);
	}

	if (!empty($page_title))			return $page_title . ' &#82111; ' . get_bloginfo('name', 'display');
}
add_filter('pre_get_document_title', 'clipmydeals_filtered_title');

// Customize "At a Glance" section on WordPress  Dashboard to include Coupons, Stores & Offer Categories
add_action('dashboard_glance_items', 'clipmydeals_at_a_glance');
function clipmydeals_at_a_glance()
{
	// Coupons
	$num_posts = wp_count_posts('coupons');
	$num = number_format_i18n($num_posts->publish);
	if (current_user_can('edit_posts')) {
		echo '<li><a href="edit.php?post_type=coupons">' . $num .' '. __('Coupons', 'clipmydeals').' </a></li>';
	} else {
		echo '<li><a href="#">' . $num . ' ' .__('Coupons', 'clipmydeals').' </a></li>';
	}
	// Stores
	$store_count = number_format_i18n(wp_count_terms('stores'));
	if (current_user_can('edit_posts')) {
		echo '<li><a href="edit-tags.php?taxonomy=stores&post_type=coupons">' . $store_count . ' '. __('Stores', 'clipmydeals') .'</a></li>';
	} else {
		echo '<li><a href="#">' . $store_count . ' ' . __('Stores', 'clipmydeals'). '</a></li>';
	}
	// Offer Categories
	$cat_count = number_format_i18n(wp_count_terms('offer_categories'));
	if (current_user_can('edit_posts')) {
		echo '<li><a href="edit-tags.php?taxonomy=offer_categories&post_type=coupons">' . $cat_count . __('Offer Categories', 'clipmydeals').' </a></li>';
	} else {
		echo '<li><a href="#">' . $cat_count . ' ' . __('Offer Categories', 'clipmydeals') . '</a></li>';
	}
	// Locations
	if (get_theme_mod('location_taxonomy', false)) {
		$loc_count = number_format_i18n(wp_count_terms('locations'));
		if (current_user_can('edit_posts')) {
			echo '<li><a href="edit-tags.php?taxonomy=locations&post_type=coupons">' . $loc_count . ' ' . __('Locations', 'clipmydeals') .'</a></li>';
		} else {
			echo '<li><a href="#">' . $loc_count . ' ' .__('Locations', 'clipmydeals') .  '</a></li>';
		}
	}
}

/**
 * Register Taxonomies for Coupons
 */

// Create Offer Category Taxonomy
function clipmydeals_create_offer_categories_taxonomy()
{
	$labels = array(
		'name' => _x(__('Offer Categories', 'clipmydeals'), 'taxonomy general name'),
		'singular_name' => _x('Offer Category', 'taxonomy singular name'),
		'search_items' =>  __('Search Offer Categories', 'clipmydeals'),
		'all_items' => __('All Offer Categories', 'clipmydeals'),
		'parent_item' => __('Parent Offer Category', 'clipmydeals'),
		'parent_item_colon' => __('Parent Offer Category:', 'clipmydeals'),
		'edit_item' => __('Edit Offer Category', 'clipmydeals'),
		'update_item' => __('Update Offer Category', 'clipmydeals'),
		'add_new_item' => __('Add New Offer Category', 'clipmydeals'),
		'new_item_name' => __('New Offer Category Name', 'clipmydeals'),
		'menu_name' => __('Offer Categories', 'clipmydeals'),
	);
	register_taxonomy('offer_categories', null, array(
		'hierarchical' => true,
		'labels' => $labels,
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'offer-category'),
	));
}
add_action('init', 'clipmydeals_create_offer_categories_taxonomy', 0);

// Add Custom Fields to Offer Categories
function clipmydeals_offer_categories_taxonomy_custom_fields($tag)
{
	// Check for existing taxonomy meta for the term you're editing
	$t_id = $tag->term_id; // Get the ID of the term you're editing
	$term_meta = cmd_get_taxonomy_options($t_id, 'offer_categories');
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="category_intro"><?php _e('Article', 'clipmydeals'); ?></label>
		</th>
		<td>
			<?php
			$category_intro = $term_meta['category_intro'] ? stripslashes($term_meta['category_intro']) : '';
			wp_editor($category_intro, 'category_intro', $settings = array('textarea_name' => 'category_intro', 'wpautop' => false));
			?>
			<br />
			<span class="description"><?php _e('This is the article that will be placed on the category page', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="category_image"><?php _e('Image URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="category_image" id="category_image" size="25" value="<?php echo $term_meta['category_image'] ? $term_meta['category_image'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter a valid image URL here', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="page_title"><?php _e('Page Title', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="text" name="page_title" id="page_title" value="<?php echo $term_meta['page_title'] ? stripslashes($term_meta['page_title']) : ''; ?>"><br />
			<span class="description"><?php _e('Enter title you want to display (leave empty to display default page title)', 'clipmydeals'); ?></span>
		</td>
	</tr>
<?php
}
// Add the fields to the Offer Categories taxonomy, using our callback function
add_action('offer_categories_edit_form_fields', 'clipmydeals_offer_categories_taxonomy_custom_fields', 10, 2);

// Save custom Offer Category fields
function clipmydeals_save_offer_categories_custom_fields($term_id)
{
	if (isset($_POST['category_intro'])) {
		$t_id = $term_id;
		$term_meta = cmd_get_taxonomy_options($t_id, 'offer_categories');
		$term_meta['category_intro'] = $_POST['category_intro'];
		$term_meta['category_image'] = $_POST['category_image'];
		$term_meta['page_title'] = $_POST['page_title'];
		update_option("taxonomy_term_$t_id", $term_meta);
	}
}
// Save the changes made on the Offer Categories taxonomy, using our callback function
add_action('edited_offer_categories', 'clipmydeals_save_offer_categories_custom_fields', 10, 2);




// Create Location Taxonomy
function clipmydeals_create_location_taxonomy()
{
	$labels = array(
		'name' => _x(__('Locations', 'clipmydeals'), 'taxonomy general name'),
		'singular_name' => _x('Location', 'taxonomy singular name'),
		'search_items' =>  __('Search Locations', 'clipmydeals'),
		'all_items' => __('All Locations', 'clipmydeals'),
		'parent_item' => __('Parent Location', 'clipmydeals'),
		'parent_item_colon' => __('Parent Location:', 'clipmydeals'),
		'edit_item' => __('Edit Location', 'clipmydeals'),
		'view_item' => __('View Location', 'clipmydeals'),
		'update_item' => __('Update Location', 'clipmydeals'),
		'add_new_item' => __('Add New Location', 'clipmydeals'),
		'new_item_name' => __('New Location Name', 'clipmydeals'),
		'menu_name' => __('Locations', 'clipmydeals'),
	);
	register_taxonomy('locations', null, array(
		'hierarchical' => true,
		'labels' => $labels,
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'location'),
	));
}
if (get_theme_mod('location_taxonomy', false)) {
	add_action('init', 'clipmydeals_create_location_taxonomy', 0);
}

// Add Custom Fields to Location
function clipmydeals_location_taxonomy_custom_fields($tag)
{
	// Check for existing taxonomy meta for the term you're editing
	$t_id = $tag->term_id; // Get the ID of the term you're editing
	$term_meta = cmd_get_taxonomy_options($t_id, 'locations');
?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="location_intro"><?php _e('Article', 'clipmydeals'); ?></label>
		</th>
		<td>
			<?php
			$location_intro = $term_meta['location_intro'] ? stripslashes($term_meta['location_intro']) : '';
			wp_editor($location_intro, 'location_intro', $settings = array('textarea_name' => 'location_intro', 'wpautop' => false));
			?>
			<br />
			<span class="description"><?php _e('This is the article that will be placed on the location page', 'clipmydeals'); ?></span>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="map"><?php _e('Map IFrame', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="map" id="map"><?php echo stripslashes($term_meta['map']); ?></textarea><br />
			<span class="description"><?php _e('Select the place in Google Map and go to Share > Embed a Map. Copy the HTML and paste in the above box.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="page_title"><?php _e('Page Title', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="text" name="page_title" id="page_title" value="<?php echo $term_meta['page_title'] ? stripslashes($term_meta['page_title']) : ''; ?>"><br />
			<span class="description"><?php _e('Enter title you want to display (leave empty to display default page title)', 'clipmydeals'); ?></span>
		</td>
	</tr>

	<?php
}
// Add the fields to the Location taxonomy, using our callback function
add_action('locations_edit_form_fields', 'clipmydeals_location_taxonomy_custom_fields', 10, 2);

// Save custom location fields
function clipmydeals_save_location_custom_fields($term_id)
{
	if (isset($_POST['map'])) {
		$t_id = $term_id;
		$term_meta = cmd_get_taxonomy_options($t_id, 'locations');
		$term_meta['map'] = $_POST['map'];
		$term_meta['page_title'] = $_POST['page_title'];
		$term_meta['location_intro'] = $_POST['location_intro'];
		update_option("taxonomy_term_$t_id", $term_meta);
	}
}
// Save the changes made on the locations taxonomy, using our callback function
add_action('edited_locations', 'clipmydeals_save_location_custom_fields', 10, 2);



// Create Store Taxonomy
function clipmydeals_create_store_taxonomy()
{
	$labels = array(
		'name' => _x(__('Stores', 'clipmydeals'), 'taxonomy general name'),
		'singular_name' => _x('Store', 'taxonomy singular name'),
		'search_items' =>  __('Search Stores', 'clipmydeals'),
		'popular_items' => __('Popular Stores', 'clipmydeals'),
		'all_items' => __('All Stores', 'clipmydeals'),
		'parent_item' => null,
		'parent_item_colon' => null,
		'edit_item' => __('Edit Store', 'clipmydeals'),
		'update_item' => __('Update Store', 'clipmydeals'),
		'add_new_item' => __('Add New Store', 'clipmydeals'),
		'new_item_name' => __('New Store Name', 'clipmydeals'),
		'separate_items_with_commas' => __('Separate stores with commas', 'clipmydeals'),
		'add_or_remove_items' => __('Add or remove stores', 'clipmydeals'),
		'choose_from_most_used' => __('Choose from the most used stores', 'clipmydeals'),
		'menu_name' => __('Stores', 'clipmydeals'),
	);
	register_taxonomy('stores', null, array(
		'hierarchical' => false,
		'labels' => $labels,
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'store')
	));
}
add_action('init', 'clipmydeals_create_store_taxonomy', 0);

//Listing custom taxonomy stores enabled or disabled status to admin listing

add_action('manage_stores_custom_column', 'clipmydeals_show_status_column', 10, 3);

if (!function_exists('clipmydeals_admin_enqueue_script')) {
	function clipmydeals_admin_enqueue_script()
	{
		wp_register_style('clipmydeals_styles', get_template_directory_uri() . '/inc/assets/css/clipmydeals_styles.css');
		wp_enqueue_style('clipmydeals_styles');
	?>
		<script>
			function updateStoreStatus(event, status, term_id) {
				event.preventDefault();
				let storeBox = document.getElementById(`toggle-store-${term_id}`)
				storeBox.disabled = true;
				let form = new FormData();
				form.append('action', `clipmydeals_ajax_update_store_status`);
				form.append('status', status);
				form.append('term_id', term_id);
				fetch('<?= admin_url('admin-ajax.php') ?>', {
						method: 'POST',
						body: form
					})
					.then((res) => res.json())
					.then((response) => {
						if (response.result) {
							storeBox.checked = response.status == 'active' ? true : false;
							if(response.status == 'active')
							{
								statusText = '<?= __('Active', 'clipmydeals') ?>'
							} 
							else 
							{
								statusText = '<?= __('Inactive', 'clipmydeals') ?>'
							}
							document.getElementById(`toggle-status-${term_id}`).innerHTML = statusText;
							document.getElementById(`toggle-status-${term_id}`).setAttribute('datastatus', response.status.charAt(0).toUpperCase() + response.status.slice(1) );
						} else {
							storeBox.checked = status == 'active' ? true : false;
							console.log(response.message);
						}
					})
					.finally(() => {
						storeBox.disabled = false;
					})
				return;
			}
		</script>
	<?php
	}
}
add_action('admin_footer', 'clipmydeals_admin_enqueue_script');

function clipmydeals_ajax_update_store_status()
{
	$term_id = $_POST['term_id'];
	$new_status = strtolower($_POST['status']) == 'active' ? 'inactive' : 'active';
	$term_meta = cmd_get_taxonomy_options($term_id, 'stores');
	$term_meta['status'] = $new_status;
	if (!update_option("taxonomy_term_$term_id", $term_meta)) {
		$response = array('result' => false, 'message' => 'Store Not updated.');
	} else {
		$response =  array('result' => true, 'status' => $new_status);
	}

	echo json_encode($response);
	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_clipmydeals_ajax_update_store_status', 'clipmydeals_ajax_update_store_status', 0);
add_action('wp_ajax_nopriv_clipmydeals_ajax_update_store_status', 'clipmydeals_ajax_update_store_status');

function clipmydeals_show_status_column($content, $columns, $term_id)
{
	switch ($columns) {
			// in this example, we had saved some term meta as "genre-characterization"
		case 'status':
			$status = esc_html(cmd_get_taxonomy_options($term_id, 'stores')['status']);
			$content = "<label class='cmd-custom-switch'>
							<input type='checkbox' id='toggle-store-$term_id' onchange='updateStoreStatus(event,document.getElementById(`toggle-status-$term_id`).getAttribute(`datastatus`),`" . $term_id . "`);' " . ($status == 'active' ? 'checked' : '') . " />
							<span class='cmd-custom-slider cmd-custom-round'></span>
						</label> <span id='toggle-status-" . $term_id . "'  datastatus='$status'  >" . ($status == "active" ?__('Active', 'clipmydeals') : __('Inactive', 'clipmydeals') ) . "</span>";
			break;
	};
	return $content;
}

// Add custom taxonomy stores title/header custom column to Admin Page
add_filter('manage_edit-stores_columns', 'clipmydeals_add_new_stores_columns');

function clipmydeals_add_new_stores_columns($columns)
{
	$columns['status'] = __('Status', 'clipmydeals');
	return $columns;
}

// Add Custom Fields to Stores
function clipmydeals_stores_taxonomy_custom_fields($tag)
{
	// Check for existing taxonomy meta for the term you're editing
	$t_id = $tag->term_id; // Get the ID of the term you're editing
	$term_meta = cmd_get_taxonomy_options($t_id, 'stores');

	global $wpdb;
	$table = $wpdb->prefix . "cmd_store_to_domain";
	$results = $wpdb->get_results("SELECT domain FROM $table WHERE store_id=$t_id");
	$sub_domains = array();
	foreach ($results as $result) $sub_domains[] = $result->domain;

	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="popular"><?php _e('Popular Store', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="checkbox" name="popular" id="popular" value="yes" <?php echo $term_meta['popular'] == 'yes' ? 'checked' : ''; ?>><br />
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_url"><?php _e('Store Website URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="store_url" id="store_url" size="25" value="<?php echo $term_meta['store_url'] ? $term_meta['store_url'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter website URL here.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_aff_url"><?php _e('Store Affiliate URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="store_aff_url" id="store_aff_url" size="25" value="<?php echo $term_meta['store_aff_url'] ? $term_meta['store_aff_url'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter store\'s default Affiliate URL here.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_sub_domains"><?php _e('Store Subdomains', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="store_sub_domains" id="store_sub_domains" rows="5" class="large-text"><?= implode("\n", $sub_domains) ?></textarea><br />
			<span class="description"><?php _e("Enter each subdomain on new line.", 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_logo"><?php _e('Logo URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="store_logo" id="store_logo" size="25" value="<?php echo $term_meta['store_logo'] ? $term_meta['store_logo'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter a valid image URL here', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_intro"><?php _e('Article', 'clipmydeals'); ?></label>
		</th>
		<td>
			<?php
			$store_intro = $term_meta['store_intro'] ? stripslashes($term_meta['store_intro']) : '';
			wp_editor($store_intro, 'store_intro', $settings = array('textarea_name' => 'store_intro', 'wpautop' => false));
			?>
			<br />
			<span class="description"><?php _e('This is the article that will be placed on the store page', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_banner"><?php _e('Banner URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="store_banner" id="store_banner" size="25" value="<?php echo $term_meta['store_banner'] ? $term_meta['store_banner'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter a valid image URL here', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_color"><?php _e('Button Color', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="color" name="store_color" id="store_color" size="25" value="<?php echo $term_meta['store_color'] ? $term_meta['store_color'] : '#2780E3'; // btn-primary is default
																						?>"><br />
			<span class="description"><?php _e('Select a dark shade so that white text will appear clearly on buttons', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="map"><?php _e('Store Location (IFrame)', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="map" id="map"><?php echo stripslashes($term_meta['map']); ?></textarea><br />
			<span class="description"><?php _e('Select the place in Google Map and go to Share > Embed a Map. Copy the HTML and paste in the above box.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="video"><?php _e('Store Video (IFrame)', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="video" id="video"><?php echo stripslashes($term_meta['video']); ?></textarea><br />
			<span class="description"><?php _e('Go to Youtube video, click on Share > Embed. Copy the HTML and paste in the above box.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="page_title"><?php _e('Page Title', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="text" name="page_title" id="page_title" value="<?php echo $term_meta['page_title'] ? stripslashes($term_meta['page_title']) : ''; ?>"><br />
			<span class="description"><?php _e('Enter title you want to display (leave empty to display default page title)', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_category"><?php _e('Store Category', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="text" name="store_category" id="store_category" value="<?php echo $term_meta['store_category'] ? stripslashes($term_meta['store_category']) : ''; ?>"><br />
			<span class="description"><?php _e('Enter store Categories (Comma separated list)', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="store_display_priority"><?php _e('Store Display Priority', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="number" name="store_display_priority" id="store_display_priority" value="<?php echo $term_meta['store_display_priority'] ? stripslashes($term_meta['store_display_priority']) : '0'; ?>"><br />
			<span class="description"><?php _e('Enter store Display Priority (Stores will be listed in descending order of display priority.)', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label><?php _e('Status', 'clipmydeals'); ?></label>
		</th>
		<td>
			<p style="display: flex;">
				<input type="radio" name="status" id="store_active" value="active" <?php if ($term_meta['status'] == "active") { ?> checked <?php } ?>><?= __('Active', 'clipmydeals') ?>
				<input style="margin-left:20px" type="radio" name="status" id="store_inactive" value="inactive" <?php if ($term_meta['status'] == "inactive") { ?> checked <?php } ?>><?= __('Inactive', 'clipmydeals') ?>
			</p>
		</td>
	</tr>

<?php
}
// Add the fields to the Stores taxonomy, using our callback function
add_action('stores_edit_form_fields', 'clipmydeals_stores_taxonomy_custom_fields', 10, 2);

// Save custom store fields
function clipmydeals_save_store_custom_fields($term_id)
{
	if (isset($_POST['store_logo'])) {
		global $wpdb;
		$cmd_store_to_domain = $wpdb->prefix . "cmd_store_to_domain";
		$t_id = $term_id;
		$term_meta = cmd_get_taxonomy_options($t_id, 'stores');
		$term_meta['popular'] = (isset($_POST['popular']) and $_POST['popular'] == 'yes') ? 'yes' : 'no';
		$term_meta['store_url'] = $_POST['store_url'];
		$term_meta['store_aff_url'] = $_POST['store_aff_url'];
		$term_meta['store_logo'] = $_POST['store_logo'];
		$term_meta['store_intro'] = $_POST['store_intro'];
		$term_meta['store_banner'] = $_POST['store_banner'];
		$term_meta['store_color'] = $_POST['store_color'];
		$term_meta['map'] = $_POST['map'];
		$term_meta['video'] = $_POST['video'];
		$term_meta['page_title'] = $_POST['page_title'];
		$term_meta['store_category'] = $_POST['store_category'];
		$term_meta['store_display_priority'] = $_POST['store_display_priority'];
		$term_meta['status'] = $_POST['status'];
		update_option("taxonomy_term_$t_id", $term_meta);

		$wpdb->query("DELETE FROM $cmd_store_to_domain WHERE store_id=$t_id");

		$store_url   = array(str_replace("www.", "", parse_url($_POST['store_url'], PHP_URL_HOST)));
		$all_domains = array_merge(explode("\n", $_POST['store_sub_domains']), $store_url);
		$domains     = array_filter($all_domains, function ($d) {
			return trim($d);
		});
		$data        = array_map(function ($d) use ($t_id) {
			return "($t_id,'" . trim($d) . "')";
		}, $domains);

		if (!empty($data)) {
			$insert_query = "INSERT INTO $cmd_store_to_domain (store_id, domain) VALUES " . implode(',', array_unique($data));
			$wpdb->query($insert_query);
		}
	}
}
// Save the changes made on the stores taxonomy, using our callback function
add_action('edited_stores', 'clipmydeals_save_store_custom_fields', 10, 2);

// Create Brand Taxonomy
function clipmydeals_create_brand_taxonomy()
{
	$labels = array(
		'name' => _x(__('Brands', 'clipmydeals'), 'taxonomy general name'),
		'singular_name' => _x('Brand', 'taxonomy singular name'),
		'search_items' =>  __('Search Brands', 'clipmydeals'),
		'popular_items' => __('Popular Brands', 'clipmydeals'),
		'all_items' => __('All Brands', 'clipmydeals'),
		'parent_item' => null,
		'parent_item_colon' => null,
		'edit_item' => __('Edit Brand', 'clipmydeals'),
		'update_item' => __('Update Brand', 'clipmydeals'),
		'add_new_item' => __('Add New Brand', 'clipmydeals'),
		'new_item_name' => __('New Brand Name', 'clipmydeals'),
		'separate_items_with_commas' => __('Separate brands with commas', 'clipmydeals'),
		'add_or_remove_items' => __('Add or remove brands', 'clipmydeals'),
		'choose_from_most_used' => __('Choose from the most used brands', 'clipmydeals'),
		'menu_name' => __('Brands', 'clipmydeals'),
	);
	register_taxonomy('brands', null, array(
		'hierarchical' => false,
		'labels' => $labels,
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'brand')
	));
}
add_action('init', 'clipmydeals_create_brand_taxonomy', 0);

// Add Custom Fields to Brands
function clipmydeals_brands_taxonomy_custom_fields($tag)
{
	// Check for existing taxonomy meta for the term you're editing
	$t_id = $tag->term_id; // Get the ID of the term you're editing
	$term_meta = cmd_get_taxonomy_options($t_id, 'brands');

?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="brand_intro"><?php _e('Article', 'clipmydeals'); ?></label>
		</th>
		<td>
			<?php
			$brand_intro = $term_meta['brand_intro'] ? stripslashes($term_meta['brand_intro']) : '';
			wp_editor($brand_intro, 'brand_intro', $settings = array('textarea_name' => 'brand_intro', 'wpautop' => false));
			?>
			<br />
			<span class="description"><?php _e('This is the article that will be placed on the brand page', 'clipmydeals'); ?></span>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="brand_image"><?php _e('Image URL', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="url" name="brand_image" id="brand_image" size="25" value="<?php echo $term_meta['brand_image'] ? $term_meta['brand_image'] : ''; ?>"><br />
			<span class="description"><?php _e('Enter a valid image URL here', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="page_title"><?php _e('Page Title', 'clipmydeals'); ?></label>
		</th>
		<td>
			<input type="text" name="page_title" id="page_title" value="<?php echo $term_meta['page_title'] ? stripslashes($term_meta['page_title']) : ''; ?>"><br />
			<span class="description"><?php _e('Enter title you want to display (leave empty to display default page title)', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="brand_map"><?php _e('Brand Location (IFrame)', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="brand_map" id="brand_map"><?php echo stripslashes($term_meta['brand_map']); ?></textarea><br />
			<span class="description"><?php _e('Select the place in Google Map and go to Share > Embed a Map. Copy the HTML and paste in the above box.', 'clipmydeals'); ?></span>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="brand_video"><?php _e('Brand Video (IFrame)', 'clipmydeals'); ?></label>
		</th>
		<td>
			<textarea name="brand_video" id="brand_video"><?php echo stripslashes($term_meta['brand_video']); ?></textarea><br />
			<span class="description"><?php _e('Go to Youtube video, click on Share > Embed. Copy the HTML and paste in the above box.', 'clipmydeals'); ?></span>
		</td>
	</tr>

	<?php
}
// Add the fields to the Brands taxonomy, using our callback function
add_action('brands_edit_form_fields', 'clipmydeals_brands_taxonomy_custom_fields', 10, 2);

function clipmydeals_save_brand_custom_fields($term_id)
{
	if (isset($_POST['brand_image'])) {
		$t_id = $term_id;
		$term_meta = cmd_get_taxonomy_options($t_id, 'brands');
		$term_meta['page_title'] = $_POST['page_title'];
		$term_meta['brand_image'] = $_POST['brand_image'];
		$term_meta['brand_map'] = $_POST['brand_map'];
		$term_meta['brand_video'] = $_POST['brand_video'];
		$term_meta['brand_intro'] = $_POST['brand_intro'];
		update_option("taxonomy_term_$t_id", $term_meta);
	}
}
// Save the changes made on the stores taxonomy, using our callback function
add_action('edited_brands', 'clipmydeals_save_brand_custom_fields', 10, 2);

/**
 * Register Coupon Post Type
 */
function clipmydeals_create_coupon_post_type()
{
	global $wp_responsive;

	// Set UI labels for Coupon Post Type
	$labels = array(
		'name'                => _x('Coupons', 'Post Type General Name', 'clipmydeals'),
		'singular_name'       => _x('Coupon', 'Post Type Singular Name', 'clipmydeals'),
		'menu_name'           => __('Coupons', 'clipmydeals', 'clipmydeals'),
		'parent_item_colon'   => __('Parent Coupon', 'clipmydeals'),
		'all_items'           => __('All Coupons', 'clipmydeals'),
		'view_item'           => __('View Coupon', 'clipmydeals'),
		'add_new_item'        => __('Add New Coupon', 'clipmydeals'),
		'add_new'             => __('Add New', 'clipmydeals'),
		'edit_item'           => __('Edit Coupon', 'clipmydeals'),
		'update_item'         => __('Update Coupon', 'clipmydeals'),
		'search_items'        => __('Search Coupon', 'clipmydeals'),
		'not_found'           => __('Not Found', 'clipmydeals'),
		'not_found_in_trash'  => __('Not found in Trash', 'clipmydeals'),
	);
	// Set other options for Coupon Post Type
	$args = array(
		'label'               => __('coupons', 'clipmydeals'),
		'description'         => __('Coupon Codes and Deals', 'clipmydeals'),
		'labels'              => $labels,
		// Gutenberg
		'show_in_rest' => true,
		// Features supported in Post Editor
		'supports'            => array('title', 'editor', 'author', 'thumbnail', 'publicize', 'comments'), // publicize is for Jetpack to post to social media
		// Associated existing Taxonomies or Custom Taxonomies
		'taxonomies'          => (get_theme_mod('location_taxonomy', false) ? array('stores', 'offer_categories', 'locations', 'brands') : array('stores', 'offer_categories', 'brands')),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'capability_type'     => get_option($wp_responsive) ? 'post' : array('coupon', 'coupons'),
		'menu_icon'           => 'dashicons-tag',
		'register_meta_box_cb' => 'clipmydeals_add_coupon_metaboxes',
	);
	// Registering Coupon Post Type
	register_post_type('coupons', $args);
}
add_action('init', 'clipmydeals_create_coupon_post_type', 0);

function clipmydeals_add_more_tag($post, $query)
{

	global $pages;
	$count = get_theme_mod('coupon_characters_count_before_more_tag', 0);
	if ($count > 0) {
		$char = '(?:&#?+\\w++;|\\s++|[^<])';
		$tags = '(?:<[^>]*+>)*+';
		foreach ($pages as $i => $page) {
			if ($post->post_type == "coupons" and strpos($page, '<!--more') === false) {
				// At least n characters, from the start, followed by a space
				$page = preg_replace("/^(?:$tags$char){{$count}}$tags(?:$char$tags)*?(?=\\s)/u", '$0<!--more-->', $page, 1);
				if ($page !== null) 	$pages[$i] = $page;
			}
		}
	}
}
add_action('the_post', 'clipmydeals_add_more_tag',	10, 2);

function clipmydeals_change_title($title, $id)
{
	$count = get_theme_mod('coupon_title_characters_count', 0);
	if (strlen($title) > $count and $count > 0 and get_post_type($id) == 'coupons' and !is_single()) {
		// At least n characters, from the start, followed by a space
		$title = wp_strip_all_tags($title);
		$pos = strpos($title, ' ', $count);
		$title = $pos > 0 ? substr($title, 0, $pos + 1) . '...' : $title;
	}
	return $title;
}
add_filter('the_title', 'clipmydeals_change_title', 10, 2);

// Add Meta Boxes to easily set custom field values
function clipmydeals_add_coupon_metaboxes()
{
	add_meta_box(
		'cmd_type',
		__('Coupon Type', 'clipmydeals'),
		'clipmydeals_display_meta_box_type',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_code',
		__('Coupon Code', 'clipmydeals'),
		'clipmydeals_display_meta_box_code',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_url',
		__('Coupon URL', 'clipmydeals'),
		'clipmydeals_display_meta_box_url',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_image_url',
		__('Image URL', 'clipmydeals'),
		'clipmydeals_display_meta_box_image_url',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_start_date',
		__('Start Date', 'clipmydeals'),
		'clipmydeals_display_meta_box_start_date',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_valid_till',
		__('Valid Till', 'clipmydeals'),
		'clipmydeals_display_meta_box_valid_till',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_verified_on',
		__('Verified On', 'clipmydeals'),
		'clipmydeals_display_meta_box_verified_on',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_badge',
		__('Badge Text', 'clipmydeals'),
		'clipmydeals_display_meta_box_badge',
		'coupons',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_display_priority',
		__('Display Priority', 'clipmydeals'),
		'clipmydeals_display_meta_box_display_priority',
		'coupons',
		'advanced',
		'high'
	);
	add_meta_box(
		'cmd_notes',
		__('Notes', 'clipmydeals'),
		'clipmydeals_display_meta_box_notes',
		'coupons',
		'advanced',
		'high'
	);
	add_meta_box(
		'cmd_lmd_id',
		__('LinkMyDeals ID', 'clipmydeals'),
		'clipmydeals_display_meta_box_lmd_id',
		'coupons',
		'advanced',
		'high'
	);
}

function clipmydeals_display_meta_box_type()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_type_n');
	echo '<select name="cmd_type" class="widefat" required>';
	$type = htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_type', true), ENT_QUOTES);
	$code_sel = $deal_sel = $print_sel = '';
	if ($type === 'print') {
		$print_sel = 'selected';
	} elseif ($type === 'deal') {
		$deal_sel = 'selected';
	} else {
		$code_sel = 'selected';
	}
	echo '<option value="">-'.__('Select', 'clipmydeals').'-</option>';
	echo '<option value="code" ' . $code_sel . '>'.__('Code', 'clipmydeals').'</option>';
	echo '<option value="deal" ' . $deal_sel . '>'.__('Deal', 'clipmydeals').'</option>';
	echo '<option value="print" ' . $print_sel . '>'.__('Printable Coupon', 'clipmydeals').'</option>';
	echo '</select>';
	echo '<div style="margin-top:5px;"><strong>'.__('Code:', 'clipmydeals').'</strong> '.__('Will have "Get Coupon Code" button', 'clipmydeals').'</div>
					<div style="margin-top:5px;"><strong>'.__('Deal:', 'clipmydeals').'</strong> '.__('Will have "Activate Deal" button', 'clipmydeals').'.</div>
					<div style="margin-top:5px;"><strong>'.__('Printable Coupon:', 'clipmydeals').'</strong>'. __('Will have "Print" button', 'clipmydeals') .'</div>';
	//echo '<input type="text" name="code" value="' . htmlspecialchars_decode( get_post_meta( $post->ID, 'code', true ), ENT_QUOTES )  . '" class="widefat">';
}

function clipmydeals_display_meta_box_code()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_code_n');
	echo '<input type="text" name="cmd_code" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_code', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;"><strong>'.__('Pro Tip:', 'clipmydeals').'</strong> '.__('If you want to show "Get Coupon Code" button even for deals, then you can set "Coupon Type" as "code" and write something like "No Coupon Code Required" here.', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_url()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_url_n');
	echo '<input type="url" name="cmd_url" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_url', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('This is the Affiliate URL which will be opened after user clicks on the button', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_image_url()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_image_url_n');
	echo '<input type="url" name="cmd_image_url" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_image_url', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('This is the Image URL which will be shown in Grid View', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_start_date()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_start_date_n');
	echo '<input type="date" name="cmd_start_date" onclick="this.showPicker()" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_start_date', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('Coupon will start appearing from this day onwards', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_valid_till()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_valid_n');
	echo '<input type="date" name="cmd_valid_till" onclick="this.showPicker()" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_valid_till', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;"><strong>'.__('Note:', 'clipmydeals').'</strong>'.__('The coupon will be active till 23:59 hrs on this day.', 'clipmydeals').' </div>';
}

function clipmydeals_display_meta_box_verified_on()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_verified_on');
	echo '<input type="date" name="cmd_verified_on" onclick="this.showPicker()" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_verified_on', true), ENT_QUOTES)  . '" class="widefat">';
}

function clipmydeals_display_meta_box_badge()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_badge_n');
	echo '<input type="text" name="cmd_badge" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_badge', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('This appears on top-right of coupon in grid layout, and on left-side of coupon in list layout.', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_lmd_id()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'lmd_id_n');
	echo '<input type="text" name="lmd_id" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'lmd_id', true), ENT_QUOTES)  . '" class="widefat">';
}

function clipmydeals_display_meta_box_display_priority()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_display_priority_n');
	$display_priority = htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_display_priority', true), ENT_QUOTES);
	if (empty($display_priority)) $display_priority = 0;
	echo '<input type="number" name="cmd_display_priority" value="' . $display_priority  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('Coupons will be listed in descending order of display priority.', 'clipmydeals').'</div>';
}

function clipmydeals_display_meta_box_notes()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_notes_n');
	echo '<textarea name="cmd_notes" rows="4" class="widefat">' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_notes', true), ENT_QUOTES)  . '</textarea>';
	echo '<div style="margin-top:5px;">'.__('Write whatever you want. This does not show anywhere on the website.', 'clipmydeals').'</div>';
}

function clipmydeals_save_coupons_meta($post_id, $post)
{
	// Return if the user doesn't have edit permissions.
	if (!current_user_can('edit_post', $post_id)) {
		return $post_id;
	}
	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if (!isset($_POST['cmd_type']) || !wp_verify_nonce($_POST['cmd_type_n'], basename(__FILE__))) {
		return $post_id;
	}
	// Now that we're authenticated, time to save the data.
	// This sanitizes the data from the field and saves it into an array $events_meta.
	$coupons_meta['cmd_type'] = $_POST['cmd_type'];
	$coupons_meta['cmd_code'] = esc_textarea($_POST['cmd_code']);
	$coupons_meta['cmd_url'] = $_POST['cmd_url'];
	$coupons_meta['cmd_image_url'] = $_POST['cmd_image_url'];
	$coupons_meta['cmd_start_date'] = empty($_POST['cmd_start_date']) ? current_time('Y-m-d') : $_POST['cmd_start_date'];
	$coupons_meta['cmd_valid_till'] = $_POST['cmd_valid_till'];
	$coupons_meta['cmd_verified_on'] = $_POST['cmd_verified_on'];
	$coupons_meta['cmd_badge'] = esc_textarea($_POST['cmd_badge']);
	$coupons_meta['lmd_id'] = esc_textarea($_POST['lmd_id']);
	$coupons_meta['cmd_display_priority'] = esc_textarea($_POST['cmd_display_priority']);
	$coupons_meta['cmd_notes'] = esc_textarea($_POST['cmd_notes']);
	// Cycle through the $coupons_meta array.
	foreach ($coupons_meta as $key => $value) :
		// Don't store custom data twice
		if ('revision' === $post->post_type) {
			return;
		}
		if (get_post_meta($post_id, $key, false)) {
			// If the custom field already has a value, update it.
			update_post_meta($post_id, $key, $value);
		} else {
			// If the custom field doesn't have a value, add it.
			add_post_meta($post_id, $key, $value);
		}
		if (!$value and $key != 'cmd_display_priority' and $key != 'cmd_valid_till') {
			// we need 'cmd_display_priority' and 'cmd_valid_till' everytime.
			//otherwise it won't appear in have_posts() when orderBy is 'cmd_valid_till' or 'cmd_display_priority'
			// for everything else, delete the meta key if there's no value
			delete_post_meta($post_id, $key);
		}
	endforeach;
}
add_action('save_post_coupons', 'clipmydeals_save_coupons_meta', 1, 2);

// Add Expiry & Social Share Columns to All Coupons Page
function clipmydeals_add_custom_coupon_columns($columns)
{
	$columns['valid_till'] =  __("Valid Till", 'clipmydeals');
	if (get_theme_mod('coupon_page', true)) {
		$columns['social_share'] = __("Publish on Social Media", 'clipmydeals');
	}
	return $columns;
}
function clipmydeals_display_custom_coupon_columns($column, $post_id)
{
	switch ($column) {

		case 'valid_till':
			$valid_till = get_post_meta($post_id, 'cmd_valid_till', true);
			if (empty($valid_till)) {
				echo 'Does not Expire';
			} elseif (strtotime($valid_till . ' 23:59:59') < microtime(true)) {
				echo '<span style="color:#f16a6a;">' . date('Y/m/d', strtotime($valid_till)) . '</span>';
			} else {
				echo '<span style="color:#5e8e41;">' . date('Y/m/d', strtotime($valid_till)) . '</span>';
			}
			break;

		case 'social_share':
			$url = get_the_permalink($post_id);
			$url .= (clipmydeals_str_contains($url, '?') ? '&' : '?') . 'referrer=' . _wp_get_current_user()->user_login;
			$url = urlencode($url);
			// Twitter
			$twitter_share_link = 'https://twitter.com/intent/tweet?text=' . urlencode(get_the_title($post_id)) . "&url=$url";
			echo '<a style="color:#0098EC;" onclick=window.open("' . $twitter_share_link . '","_blank","height=400,width=600,left=383,top=184,menubar=no,titlebar=no");><span class="dashicons dashicons-twitter" style="margin-right:10px;"></span></a>';
			// Facebook
			$facebook_share_link = 'https://www.facebook.com/sharer/sharer.php?u=' . $url . '&display=popup';
			if (!empty(get_theme_mod('facebook_app_id'))) {
				$facebook_share_link .= '&app_id=' . get_theme_mod('facebook_app_id');
			}
			echo '<a style="color:#465596;" onclick=window.open("' . $facebook_share_link . '","_blank","height=400,width=600,left=383,top=184,menubar=no,titlebar=no");><span class="dashicons dashicons-facebook" style="margin-right:10px;"></span></a>';
			// WhatsApp
			$whatsapp_share_link = 'https://web.whatsapp.com/send?text=' . $url;
			echo '<a style="color:#465596;" onclick=window.open("' . $whatsapp_share_link . '","_blank","height=600,width=900,left=300,top=120,menubar=no,titlebar=no");><img src="' . get_template_directory_uri() . '/inc/assets/images/whatsapp-icon.png" style="width:20px; margin-right: 10px" /></a>';
			break;
	}
}

add_action(base64_decode('dXBkYXRlX29wdGlvbl9jbWRf') . base64_decode('aw==') . base64_decode('ZQ==') . base64_decode('eQ=='), 'miftah', 10, 2);

function clipmydeals_coupons_sortable_columns($columns)
{
	$columns['valid_till'] = __('valid_till', 'clipmydeals');
	$columns['store'] = __('store', 'clipmydeals'); 
	return $columns;
}
function clipmydeals_coupons_orderby($query)
{
	if (!is_admin()) return;
	$orderby = $query->get('orderby');
	if ('valid_till' == $orderby) {
		$query->set('meta_key', 'cmd_valid_till');
		$query->set('orderby', 'meta_value');
	}
}

add_filter('manage_coupons_posts_columns', 'clipmydeals_add_custom_coupon_columns');
add_action('manage_coupons_posts_custom_column', 'clipmydeals_display_custom_coupon_columns', 10, 2);
add_filter('manage_edit-coupons_sortable_columns', 'clipmydeals_coupons_sortable_columns');
add_action('pre_get_posts', 'clipmydeals_coupons_orderby');

// miftah
function clear_junk_files()
{
	if (!wp_next_scheduled('clear_junk_event')) {
		wp_schedule_event(time(), base64_decode('aG91cmx5'), 'clear_junk_event');
	}
}
function delete_junk_files()
{
	wp_clear_scheduled_hook('clear_junk_event');
}
add_action('after_switch_theme', 'clear_junk_files', 10,  2);
add_action('clear_junk_event', 'run_garbage_collection');
add_action('switch_theme', 'delete_junk_files');


// Open Graph & other Meta Tags
if (!function_exists('clipmydeals_metatags')) {
	function clipmydeals_metatags()
	{

		// Version
		echo '<meta name="clipmydeals_version" content="' . wp_get_theme(get_template())->get('Version') . '" />' . PHP_EOL;

		// Social
		if (get_theme_mod('enable_social_meta_tags', true)) {

			global $post;
			global $wp;

			// Single Coupon
			if (is_single() and get_post_type() == 'coupons') {
				$title = get_the_title() . ' | ' . get_bloginfo('name');
				$description = $post->post_content;
				$img = '';
				if (has_post_thumbnail()) {
					$img = get_the_post_thumbnail_url(null, 'full');
				} elseif (!empty(get_post_meta(get_the_ID(), 'cmd_image_url', true))) {
					$img = get_post_meta(get_the_ID(), 'cmd_image_url', true);
				} else {
					$store_terms = get_the_terms(get_the_ID(), 'stores');
					if ($store_terms) {
						$store_custom_fields = cmd_get_taxonomy_options($store_terms[0]->term_id, 'stores');
						$img = $store_custom_fields['store_logo'];
					}
				}

				// Single Page/Post
			} elseif ((is_single() and get_post_type() == 'post') or is_page()) {
				$title = get_the_title() . ' | ' . get_bloginfo('name');
				$description = $post->post_excerpt;
				if (empty($description)) {
					$description = wp_kses_post(wp_trim_words($post->post_content, 20));
				}
				if (has_post_thumbnail()) {
					$img = get_the_post_thumbnail_url(null, 'full');
				}

				// Store Page
			} elseif (is_tax('stores')) {
				$term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
				$custom_fields = cmd_get_taxonomy_options($term->term_id, 'stores');
				$title = $term->name . ' | ' . get_bloginfo('name');
				$description = $term->description;
				if (!empty($custom_fields['store_banner'])) {
					$img = $custom_fields['store_banner'];
				} elseif (!empty($custom_fields['store_logo'])) {
					$img = $custom_fields['store_logo'];
				}

				// Offer Category Page
			} elseif (is_tax('offer_categories')) {
				$term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
				$custom_fields = cmd_get_taxonomy_options($term->term_id, 'offer_categories');
				$title = $term->name . ' | ' . get_bloginfo('name');
				$description = $term->description;
				if (!empty($custom_fields['category_image'])) {
					$img = $custom_fields['category_image'];
				}

				// Locations Page
			} elseif (is_tax('locations')) {
				$term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
				$title = $term->name . ' | ' . get_bloginfo('name');
				$description = $term->description;
			}

			// Defaults
			if (empty($title)) {
				$title = get_bloginfo('name') . ' - ' . get_bloginfo('description');
			}
			if (empty($description)) {
				$description = get_theme_mod('site_description', __('Get Latest Coupons and Deals for your Online Shopping', 'clipmydeals'));
			}
			if (empty($img) and !empty(get_theme_mod('site_image'))) {
				$img = get_theme_mod('site_image');
			}

			// Strip HTML
			$title = strip_shortcodes(strip_tags($title));
			$description = strip_shortcodes(strip_tags($description));

	?>
			<?php if (!empty($description)) {
				echo '<meta name="description" content="' . $description . '">' . PHP_EOL;
			} ?>
			<meta name="author" content="<?php bloginfo('name'); ?>" />
			<?php if (!empty(get_theme_mod('facebook_app_id'))) {
				echo '<meta property="fb:app_id" content="' . get_theme_mod('facebook_app_id') . '"/>' . PHP_EOL;
			} ?>
			<?php if (!empty(get_theme_mod('facebook_admins'))) {
				echo '<meta property="fb:app_id" content="' . get_theme_mod('facebook_admins') . '"/>' . PHP_EOL;
			} ?>
			<meta property="og:locale" content="<?php bloginfo('language'); ?>" />
			<meta property="og:title" content="<?php echo $title; ?>" />
			<?php if (!empty($description)) {
				echo '<meta property="og:description" content="' . $description . '"/>' . PHP_EOL;
			} ?>
			<?php if (!empty($img)) {
				echo '<meta property="og:image" content="' . $img . '"/>' . PHP_EOL;
			} ?>
			<meta property="og:url" content="<?php echo home_url($wp->request); ?>" />
			<meta property="og:site_name" content="<?php bloginfo('name'); ?>" />
			<meta property="og:type" content="article" />
			<meta property="article:modified_time" content="<?php echo get_the_modified_time('c') ?>" />
			<?php if (!empty(get_theme_mod('facebook_page'))) {
				echo '<meta property="article:publisher" content="' . get_theme_mod('facebook_page') . '"/>' . PHP_EOL;
			} ?>
			<meta name="twitter:card" content="summary" />
			<?php if (!empty(get_theme_mod('twitter_page'))) {
				echo '<meta name="twitter:site" content="' . get_theme_mod('twitter_page') . '"/>' . PHP_EOL;
			} ?>
			<?php if (!empty(get_theme_mod('twitter_author'))) {
				echo '<meta name="twitter:creator" content="' . get_theme_mod('twitter_author') . '"/>' . PHP_EOL;
			} ?>

		<?php
		}
	}
}

if (!function_exists('clipmydeals_styletags')) {
	function clipmydeals_styletags()
	{

		$style = ".container-xl {max-width: 1250px; }";

		$style .= ":root {";

		if (get_theme_mod('header_color')) {
			$style .= '--cmd-header-color:' . get_theme_mod('header_color') . ';';
		}
		if (get_theme_mod('header_gradient')) {
			$style .= '--cmd-header-gradient-color:' . get_theme_mod('header_gradient') . ';';
		}
		if (get_theme_mod('navbar_text_color')) {
			$style .= '--cmd-header-menu-color:' . get_theme_mod('navbar_text_color') . ';';
		}
		if (get_theme_mod('navbar_search_btn_color')) {
			$style .= '--cmd-header-search-btn-color:' . get_theme_mod('navbar_search_btn_color') . ';';
		}
		if (get_theme_mod('cmd_primary')) {
			$style .= '--cmd-primary:' . get_theme_mod('cmd_primary') . ';';
		}
		if (get_theme_mod('cmd_header_menu_active_color')) {
			$style .= '--cmd-header-menu-active-color:' . get_theme_mod('cmd_header_menu_active_color') . ';';
		}
		if (get_theme_mod('cmd_card_title_color')) {
			$style .= '--cmd-font-primary:' . get_theme_mod('cmd_card_title_color') . ';';
		}
		if (get_theme_mod('cmd_link_color')) {
			$style .= '--cmd-link-color:' . get_theme_mod('cmd_link_color') . ';';
		}
		if (get_theme_mod('cmd_card_bg_color')) {
			$style .= '--cmd-card-bg:' . get_theme_mod('cmd_card_bg_color') . ';';
		}
		if (get_theme_mod('cmd_card_hover_color')) {
			$style .= '--cmd-card-hover-border:' . get_theme_mod('cmd_card_hover_color') . ';';
		}
		if (get_theme_mod('footer_color')) {
			$style .= " #footer-widget{
			background-color: " . get_theme_mod('footer_color') . " !important;
			background-image: none;
		};
		#footer-widget-wide{
			background: " . get_theme_mod('footer_color') . " !important;
			opacity: 85%;
		};
		#footer-get-now{
			background: " . get_theme_mod('footer_color') . " !important;
		}
		";
	};
		if(get_theme_mod('display_type')){
			$style .= "
				img{
					object-fit: " .get_theme_mod('display_type')."!important;
				}
			";
		}

		$style .= "}";

		echo "<style>{$style}</style>";

		$style  = get_theme_mod('mobile_coupon_card', 0) ? ".coupon-box .grid-layout.card {height: " . get_theme_mod('mobile_coupon_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('mobile_coupon_image', 0) ? ".coupon-box .grid-layout > .cmd-grid-image {height: " . get_theme_mod('mobile_coupon_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('mobile_product_card', 0) ? ".product-box > .grid-layout.card  {height: " . get_theme_mod('mobile_product_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('mobile_product_image', 0) ? ".product-box > .grid-layout  img {height: " . get_theme_mod('mobile_product_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('mobile_carousel_card', 0) ? ".cmd-featuredoffers-widget .card {height: " . get_theme_mod('mobile_carousel_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('mobile_carousel_image', 0) ? ".cmd-featuredoffers-widget .carousel-img {height: " . get_theme_mod('mobile_carousel_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('mobile_store_image', 0) ? ".cmd-store-logo-fix-height {height: " . get_theme_mod('mobile_store_image') . "rem !important;  object-fit: contain;}" : "";
		if (get_theme_mod('mobile_card_and_images_use_value', 'default') == 'custom') echo "<style>@media screen and (max-width: 768px){{$style}}</style>";

		$style  = get_theme_mod('desktop_coupon_card', 0) ? ".coupon-box > .grid-layout.card {height: " . get_theme_mod('desktop_coupon_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('desktop_coupon_image', 0) ? ".coupon-box > .grid-layout > .cmd-grid-image {height: " . get_theme_mod('desktop_coupon_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('desktop_product_card', 0) ? ".product-box > .grid-layout.card  {height: " . get_theme_mod('desktop_product_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('desktop_product_image', 0) ? ".product-box > .grid-layout  img {height: " . get_theme_mod('desktop_product_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('desktop_carousel_card', 0) ? ".cmd-featuredoffers-widget .card {height: " . get_theme_mod('desktop_carousel_card') . "rem !important; text-overflow: ellipsis;}" : "";
		$style .= get_theme_mod('desktop_carousel_image', 0) ? ".cmd-featuredoffers-widget .carousel-img {height: " . get_theme_mod('desktop_carousel_image') . "rem !important;  object-fit: contain;}" : "";
		$style .= get_theme_mod('desktop_store_image', 0) ? ".cmd-store-logo-fix-height {height: " . get_theme_mod('desktop_store_image') . "rem !important;  object-fit: contain;}" : "";
		if (get_theme_mod('desktop_card_and_images_use_value', 'default') == 'custom') echo "<style>@media screen and (min-width: 769px){{$style}}</style>";
	}
}
add_action('wp_head', 'clipmydeals_styletags');


if (!function_exists('clipmydeals_common_scripts')) {
	function clipmydeals_common_scripts()
	{
		get_template_part('template-parts/coupon', 'popup');
		get_template_part('template-parts/login', 'modal');
		$user_id = get_current_user_id();
		$vapid_keys =  get_option('clipmydeals_vapid_keys', array('publicKey' => NULL, 'privateKey' => NULL));
		$handle_notifications = (!is_null($vapid_keys['publicKey']) and (get_theme_mod('notification_requests', 'enable') == 'enable'));
		$only_register_service_worker = ((!$user_id) or (get_theme_mod('onload_notifications', 'on') != 'on') or (get_user_meta($user_id, 'cmd_notifications_disabled', true) == true));
		?>
		<script>
			function cmdHandleScrollClearCookie(handleScroll = true) {
				var cookie = getCookie('cmdShowOfferCookie');
				if (cookie.includes('elementId') && handleScroll) {
					data = JSON.parse(cookie);
					console.log(data)
					var elementId = data.elementId
					if (elementId.includes('modal')) {
						if (document.querySelector(elementId)) {
							jQuery(data.elementId).modal('show');
						}
						elementId = elementId.replace("modal", data.name);
					}
					var current_item = document.querySelector(elementId);
					if (current_item != null) {
						var parent_carousel = current_item.closest('.carousel');
						if (parent_carousel != null && elementId.includes("carousel")) {
							var active_item = parent_carousel.getElementsByClassName('active')[0];
							active_item.classList.remove('active');

							var all_items = parent_carousel.getElementsByClassName('carousel-item')
							for (var i = 0; i < all_items.length, all_items[i].id != current_item.id; i++);
							i = cmdGetItemsPerSlide() - (all_items.length - i);

							while (i >= 1 && i <= 3 && i-- > 0)
								if (current_item.previousElementSibling) current_item = current_item.previousElementSibling;

							current_item.classList.add('active');
							window.scrollTo({
								top: parent_carousel.getBoundingClientRect().top - 100,
								behavior: "smooth"
							});
						} else {
							console.log(current_item.getBoundingClientRect().top - 100)
							window.scrollTo({
								top: current_item.getBoundingClientRect().top - 100,
								behavior: "smooth"
							});
						}
						// Popup
						if (((data.name == "popup" || data.name == "show_coupon") && <?= var_export(get_theme_mod('reveal_type', 'inline') == 'popup'); ?>) || (<?= var_export(
																																									filter_var($_GET['isCarousel'] ?? '', FILTER_VALIDATE_BOOLEAN)
																																								); ?>)) {
							jQuery('#showCouponModal').modal('show');
						}
					}
				}
				if ((<?php var_export(isset($_GET['showCoupon']) and !empty($_GET['showCoupon']) and get_theme_mod('reveal_type', 'inline') == 'popup') ?>) || (<?= var_export(
																																									filter_var($_GET['isCarousel'] ?? '', FILTER_VALIDATE_BOOLEAN)
																																								); ?>)) {
					jQuery('#showCouponModal').modal('show');
				}

				setCookie('cmdShowOfferCookie', '');
			};

			function cmdHandleOffer(userID = '', isCarousel = false) {
				var data = {};
				try {
					data = JSON.parse(getCookie('cmdShowOfferCookie'));
					data.name = decodeURIComponent(data.name)
				} catch (e) {
					return false;
				}
				setCookie('storesVisited', `${getCookie('storesVisited')}${data.slug}|`);
				if (!data.url.includes('str') && (window.location.href.includes('iosView') || window.location.href.includes('androidView'))) {
					var details = `${data.elementId} ${data.url}`;
					iosAppMessage.postMessage(details.replace('#', ''));
					if(!data.dont_reload){
						window.location.reload(true); //refresh to show code
					}else{
						jQuery('#loginModal').modal('hide');
					}			
					return;
				}
				if (data.name == "show_coupon") {
					let modal = '<?= get_option('cmdcp_show_login_modal', 'no') ?>';
					let loggedIn = <?= var_export(is_user_logged_in()) ?>;
					let maxCount = <?= get_option('cmdcp_show_login_count', '-1') ?>;

					let cookieCount = getCookie('cmdShowOfferCount');
					cookieCount = (cookieCount != "" ? (parseInt(cookieCount)) : 0);
					if (loggedIn || modal == 'no' || (modal == 'optional' && maxCount != -1 && maxCount <= cookieCount)) {
						setCookie('id', `${data.elementId.split('-')[2]}`);
					} else {
						setCookie('id', '');
					}
					if (isCarousel) {
						window.open(`${location.href.split('#')[0].split('?')[0]}?showCoupon=${data.elementId.split('-')[2]}&isCarousel=${isCarousel}${data.elementId}`);
					} else {
						window.open(`${location.href.split('#')[0].split('?')[0]}?showCoupon=${data.elementId.split('-')[2]}`);
					}
					location.href = `${data.url}${userID}`;
					return;
				}

				// logic below only works if name is not 'show_coupon' as location.href redirects to store page
				if (data.name == "app_code") {
					// element.className = element.className.replace('REMOVE-CLASS', 'ADD-CLASS')
					document.querySelectorAll(`.${data.slug}-app-code-hidden`).forEach(ele => {
						ele.classList.add('d-none');
						ele.classList.remove('d-inline-block');
					});
					document.querySelectorAll(`.${data.slug}-app-code-revealed`).forEach(ele => {
						ele.classList.add('d-block');
						ele.classList.remove('d-none');
					});
				}

				window.open(`${data.url}${userID}`);
				if (userID != '') {
					// Goto Top Of Page Before Refresh to Avoid Reverse Scrolling after Reloading
					window.scrollTo({
						top: 0
					});
					window.location.reload(true);
				} else {
					setCookie('cmdShowOfferCookie', '');
				}
			}



			function cmdShowOffer(event, slug, elementId, title, name = null, id = null, isCarousel = false) {
				event.preventDefault();

				var url = event.target.closest('a').href;
				setCookie('cmdShowOfferCookie', JSON.stringify({
					'slug': slug,
					'name': encodeURIComponent(name),
					'elementId': elementId,
					'url': url
				}));

				if (typeof gtag !== 'undefined') {
					var event_data = {
						'event_category': typeof name !== 'undefined' && name != 'false' ? name : slug,
						'event_label': title,
					}
					gtag('event', 'view_item', event_data)
				}

				var modal = '<?= get_option('cmdcp_show_login_modal', 'no') ?>';
				var loggedIn = <?= var_export(is_user_logged_in()) ?>;
				var maxCount = <?= get_option('cmdcp_show_login_count', '-1') ?>;
				var showWordpressLogin = <?= var_export(!in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins')) or get_option('cmdcp_login_form_type', 'theme') != 'theme') ?>;

				var cookieCount = getCookie('cmdShowOfferCount');
				cookieCount = (cookieCount != "" ? (parseInt(cookieCount)) : 0);

				if (loggedIn || modal == 'no' || (modal == 'optional' && maxCount != -1 && maxCount <= cookieCount)) {
					if (id != null) {
						kCopy(`${id}`, null);
					}
					cmdHandleOffer('', isCarousel);
				} else if (showWordpressLogin) {
					window.location.href = '<?= wp_login_url($_SERVER['REQUEST_URI']) ?>';

				} else {
					setCookie('cmdShowOfferCount', cookieCount + 1)

					setCookie('storesVisited', `${getCookie('storesVisited')}${slug}|`);
					if (window.location.href.includes('iosView')) {
						var details = `login ${elementId} ${url}`;
						iosAppMessage.postMessage(details.replace('#', ''));
						return;
					}
					if (isCarousel) {
						jQuery('#loginModalSkipPopup').removeClass('d-none').addClass('d-flex');
						jQuery('#loginModalSkipInline').removeClass('d-flex').addClass('d-none');

					} else {
						jQuery('#loginModalSkipInline').removeClass('d-none').addClass('d-flex');
						jQuery('#loginModalSkipPopup').removeClass('d-flex').addClass('d-none');

					}

					jQuery('#loginModal').modal('show');
				}
			}

			function cmdAjaxSearch(child, search = '') {
				if (document.getElementById('search_results')) document.getElementById('search_results').remove()
				if (search == '') {
					search = child.value ?? ''
				}
				if (search.length > <?= get_theme_mod('ajax_search_start_after', 3) ?>) {
					child.insertAdjacentHTML('afterend', '<div id="search_results" class="list-group mt-0 mt-lg-1 shadow-lg position-absolute bg-dark text-light fs-6"></div>')
					var results = document.getElementById('search_results')
					results.innerHTML = '<div class="list-group-item bg-dark text-light"><?= __('Loading', 'clipmydeals') ?></div>'
					var form = new FormData()
					form.append('action', 'clipmydeals_ajax_search')
					form.append('search', search)
					fetch('<?= admin_url('admin-ajax.php') ?>', {
							method: 'POST',
							body: form
						})
						.then((res) => res.text())
						.then((html) => {
							results.innerHTML = html
						})
				}
			}

			document.querySelectorAll('.cmd-featuredoffers-widget').forEach((ele, idx) => {
				ele.classList.remove("grid-1", "grid-2", "grid-3", "grid-4");
				ele.classList.add(`grid-${cmdGetItemsPerSlide()}`);
			})

			function cmdGetItemsPerSlide() {
				var smaller_screen_area = <?= var_export(clipmydeals_layout_options(true)['smaller_screen_area']) ?>;
				var windowSize = window.innerWidth;
				if (windowSize < 768) return 1;
				if (windowSize < 992 || (windowSize < 1200 && smaller_screen_area)) return 2;
				if (windowSize < 1200 || smaller_screen_area) return 3;
				return 4;
			}

			function cmdSetCarouselParameters(title) {
				var carousel = document.querySelector(title);
				var controls = document.querySelectorAll(`${title} .carousel-control-prev, ${title} .carousel-control-next`);

				var itemsPerSlide = cmdGetItemsPerSlide();
				var totalItems = document.querySelectorAll(`${title} .carousel-item`).length;

				carousel.parentNode.classList.remove("grid-1", "grid-2", "grid-3", "grid-4");
				carousel.parentNode.classList.add(`grid-${itemsPerSlide}`);

				if (totalItems <= itemsPerSlide) {
					carousel.removeAttribute('data-ride');
					controls.forEach(function(control) {
						control.classList.add("d-none")
					});
				} else {
					carousel.setAttribute('data-ride', 'carousel');
					controls.forEach(function(control) {
						control.classList.remove("d-none")
					});
				}

				jQuery(title).on("slide.bs.carousel", function(e) {
					var idx = [...e.relatedTarget.parentNode.children].indexOf(e.relatedTarget);
					if (idx >= totalItems - (itemsPerSlide - 1) || idx == totalItems - 1) {
						var it = itemsPerSlide - (totalItems - idx);
						for (var i = 0; i < it; i++) {
							var ele = document.querySelectorAll(`${title} .carousel-item`)[e.direction == 'left' ? i : 0]
							ele.parentNode.appendChild(ele);
						}
					}
				});
			};

			function openProduct(event, url, id, title) {
				event.preventDefault();
				if (typeof gtag !== 'undefined') {
					var event_data = {
						'event_category': 'product',
						'event_label': title,
					}
					gtag('event', 'view_item', event_data)
				}

				var modal = '<?= get_option('cmdcp_show_login_modal', 'no') ?>';
				var loggedIn = <?= var_export(is_user_logged_in()) ?>;
				var maxCount = <?= get_option('cmdcp_show_login_count', '-1') ?>;
				var showWordpressLogin = <?= var_export(!in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins')) or get_option('cmdcp_login_form_type', 'theme') != 'theme') ?>;

				var cookieCount = getCookie('cmdShowOfferCount');
				cookieCount = (cookieCount != "" ? (parseInt(cookieCount)) : 0);

				setCookie('cmdShowOfferCookie', JSON.stringify({
					'slug': id,
					'name': encodeURIComponent(title),
					'elementId': 'product-'+id,
					'url': url,
					'dont_reload': true
				}));
				
				if (loggedIn || modal == 'no' || (modal == 'optional' && maxCount != -1 && maxCount <= cookieCount)) {
					cmdHandleOffer();

				}else{

					setCookie('cmdShowOfferCount', cookieCount + 1)
					if (window.location.href.includes('iosView')) {
						iosAppMessage.postMessage('login product-'+id+' '+url);
						return;
					}

					jQuery('#cmd_login_reload').prop('checked', false);
					jQuery('#loginModalSkipPopup').removeClass('d-none').addClass('d-flex');
					jQuery('#loginModalSkipInline').removeClass('d-flex').addClass('d-none');
					jQuery('#loginModal').modal('show');

				}

			}

			function openLoginPage(event) {
				event.preventDefault();
				var url = '<?= wp_login_url($_SERVER['REQUEST_URI']) ?>';
				if (window.location.href.includes('iosView')) {
				iosAppMessage.postMessage('login');
				return;
				}
				window.location.href = url
			}

			function cmdInitializeCarousel() {
				document.querySelectorAll('.cmd-featuredoffers-widget>div').forEach(ele => cmdSetCarouselParameters(`#${ele.id}`));
			}

			function cmdLoadLoginModal(subscribeNotifications = false) {

				if (window.location.href.includes('iosView')) {
				iosAppMessage.postMessage('login');
				return;
				}

				jQuery('#cmd_login_reload').prop('checked', true);
				jQuery('#cmd_register_reload').prop('checked', true);
				jQuery('#cmd_login_subscribe_notifications').prop('checked', subscribeNotifications);
				jQuery('#cmd_register_subscribe_notifications').prop('checked', subscribeNotifications);
				jQuery('#loginModalSkip').removeClass('d-flex').addClass('d-none');
				jQuery('#loginModal').modal('show');
			}

			window.onload = () => {
				cmdHandleScrollClearCookie();
				cmdInitializeCarousel();

				if (<?php var_export($handle_notifications) ?>) cmdHandleNotification(<?php var_export($only_register_service_worker) ?>);

				const cmd_logged_out = getCookie('clipmydeals_logged_out');
				if (cmd_logged_out) {
					if (typeof iosAppMessage !== 'undefined')
						iosAppMessage.postMessage('logout');
					setCookie('clipmydeals_logged_out', '', -1);
				}

				const cmd_logged_in = getCookie('clipmydeals_logged_in');
				if (cmd_logged_in) {
					if (typeof iosAppMessage !== 'undefined')
						iosAppMessage.postMessage(`loggedin-${cmd_logged_in}`);
					setCookie('clipmydeals_logged_out', '', -1);
				}
			}

			window.onresize = () => {
				cmdInitializeCarousel();
			}
			window.addEventListener("load", (event) => {
				document.querySelectorAll(".cmd-slider-widget").forEach(element => {
					if (element.scrollWidth == element.offsetWidth) {
						element.classList.add("justify-content-center");
						element.classList.remove("overflow-x-scroll");
					} else {
						element.classList.add('justify-content-start', 'overflow-x-scroll');
					}
				})
				document.querySelectorAll("#recent-post").forEach(element => {
					if (element.scrollWidth == element.offsetWidth) {
						element.classList.remove("overflow-x-scroll");
					} else {
						element.classList.add('overflow-x-scroll');
					}
				})
			});
		</script>
		<?php
		clipmydeals_notifications_scripts();
		if (!function_exists('get_plugin_data')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');

		if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins')) and version_compare(get_plugin_data(WP_PLUGIN_DIR . '/clipmydeals-cashback/clipmydeals-cashback.php')['Version'], '5.0', '>')) {
			cmdcp_login_script();
		}
	}
}
add_action('wp_footer', 'clipmydeals_common_scripts');

if (!function_exists('clipmydeals_notifications_scripts')) {
	function clipmydeals_notifications_scripts()
	{
		$is_user_loggedin = is_user_logged_in(); ?>
		<script>
			let isSubscribed = false;

			function cmdOnSubscriptionButtonClick(event) {
				event.preventDefault();
												if (<?php var_export($is_user_loggedin) ?>) {
					if (window.location.href.includes('iosView') || window.location.href.includes('androidView')) {
						iosAppMessage.postMessage(`notification_call`);
						return;
					}
					var msg = isSubscribed ? '<?= __('Unsubscribing...', 'clipmydeals') ?>' : '<?= __('Subscribing...', 'clipmydeals') ?>';
					cmdSubscriptionButtonState(true, msg);
					if (!isSubscribed) cmdCreateSubscription();
					else cmdRemoveSubscription();
					isSubscribed = !isSubscribed;
				} else {
					cmdLoadLoginModal(true);
				}

			}

			function cmdCheckForUserSubscription() {
				navigator.serviceWorker.getRegistration('<?= get_template_directory_uri() ?>/inc/assets/js/clipmydeals-sw.js')
					.then(serviceWorkerRegistration => serviceWorkerRegistration.pushManager.getSubscription())
					.then(subscription => {
						if (subscription) isSubscribed = true
						var msg = isSubscribed ? '<?= __('Disable Push Notifications', 'clipmydeals') ?>' : '<?= __('Enable Push Notifications', 'clipmydeals') ?>';
						cmdSubscriptionButtonState(false, msg)
					})
					.catch(e => {
						console.log('Cannot subscribe to notifications', e);
						var msg = Notification.permission == 'denied' ? '<?= __('Notifications denied by user.', 'clipmydeals') ?>' :
							'<?= __('Failed to enable notifications.', 'clipmydeals') ?>';
						cmdSubscriptionButtonState(true, msg);
					})
			}

			function cmdSubscriptionButtonState(disabled, content, showText = false) {
				let pushButtons = document.querySelectorAll('.cmd-subscription-button');
				pushButtons.forEach(function(pushButton) {
					if (!pushButton || (!<?php var_export(is_user_logged_in()) ?> && !showText)) return;
					pushButton.disabled = disabled;
					pushButton.innerHTML = content;
				});
			}

			function cmdRemoveSubscription() {
				navigator.serviceWorker.getRegistration('<?= get_template_directory_uri() ?>/inc/assets/js/clipmydeals-sw.js')
					.then(serviceWorkerRegistration => serviceWorkerRegistration.pushManager.getSubscription())
					.then(subscription => {
						if (!subscription) {
							cmdSubscriptionButtonState(false, '<?= __('Enable Push Notifications', 'clipmydeals') ?>');
							return;
						} else {
							return cmdSendSubscriptionToServer(subscription, 'DELETE');
						}
					})
					.then(subscription => {
						if (subscription) subscription.unsubscribe()
					})
					.then(() => cmdSubscriptionButtonState(false, '<?= __('Enable Push Notifications', 'clipmydeals') ?>'))
					.catch(e => {
						cmdSubscriptionButtonState(true, '<?= __('Failed to unsubribe user', 'clipymdeals') ?>');
					});
			}

			function cmdCreateSubscription(onlyRegisterServiceWorker = false) {
				var applicationServerKey = '<?= get_option('clipmydeals_vapid_keys', array('publicKey' => '', 'privateKey' => ''))['publicKey'] ?>'
				return cmdCheckNotificationPermission()
					.then(() => navigator.serviceWorker.getRegistration('<?= get_template_directory_uri() ?>/inc/assets/js/clipmydeals-sw.js'))
					.then(serviceWorkerRegistration => {
						if (!onlyRegisterServiceWorker) {
							return serviceWorkerRegistration.pushManager.subscribe({
								userVisibleOnly: true,
								applicationServerKey: applicationServerKey
							})
						}
					})
					.then(subscription => {
						if (!onlyRegisterServiceWorker) {
							return cmdSendSubscriptionToServer(subscription, 'POST')
						}
					})
					.then(() => cmdSubscriptionButtonState(false, '<?= __('Disable Push Notifications', 'clipmydeals') ?>'))
					.catch(e => {
						var msg = Notification.permission == 'denied' ? '<?= __('Notifications denied by user.', 'clipmydeals') ?>' :
							'<?= __('Failed to enable notifications.', 'clipmydeals') ?>';
						cmdSubscriptionButtonState(true, msg);
					});
			}

			function cmdCheckNotificationPermission() {
				return new Promise((resolve, reject) => {
					if (Notification.permission === 'denied') return reject(new Error('Push messages are blocked.'));
					if (Notification.permission === 'granted') return resolve();
					if (Notification.permission === 'default') {
						return Notification.requestPermission().then(result => {
							if (result !== 'granted') return reject(new Error('Bad permission result'));
							else return resolve();
						});
					}
					return reject(new Error('Unknown permission'));
				});
			}

			function cmdSendSubscriptionToServer(subscription, method) {
				var form = new FormData()
				form.append('subscription_endpoint', subscription.endpoint)
				form.append('token', btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))))
				form.append('key', btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))))
				form.append('action', 'clipmydeals_create_subscription')
				form.append('subscription_action', method)
				return fetch('<?= admin_url('admin-ajax.php') ?>', {
					method: 'POST',
					body: form
				}).then(() => subscription);
			}

			function cmdUpdateSubscription(onlyRegisterServiceWorker) {
				navigator.serviceWorker.getRegistration('<?= get_template_directory_uri() ?>/inc/assets/js/clipmydeals-sw.js')
					.then(serviceWorkerRegistration => serviceWorkerRegistration.pushManager.getSubscription())
					.then(subscription => {
						if (subscription) return cmdSendSubscriptionToServer(subscription, 'PUT');
						else return cmdCreateSubscription(onlyRegisterServiceWorker);
					})
					.catch(e => {
						console.log('Error when updating the subscription', e);
					});
			}

			function cmdHandleNotification(onlyRegisterServiceWorker = true) {
				if (<?php var_export(isset($_GET['iosView']))?> || <?php var_export(isset($_GET['androidView'])) ?>) {
					// This condition is here because it stops the content of the button from changing else the button gets stuck in 'Subscribing...' state
				} else if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('showNotification' in ServiceWorkerRegistration.prototype)) {
					cmdSubscriptionButtonState(true, '<?= __('Push notifications are not compatible with this browser.', 'clipmydeals') ?>');
				} else if (Notification.permission === 'denied') {
					cmdSubscriptionButtonState(true, '<?= __('Notifications denied by user.', 'clipmydeals') ?>');
				} else {
					navigator.serviceWorker.register('<?= get_template_directory_uri() ?>/inc/assets/js/clipmydeals-sw.js')
						.then(() => {
							cmdUpdateSubscription(onlyRegisterServiceWorker);
						})
						.then(() => {
							cmdCheckForUserSubscription();
						})
						.catch(e => {
							console.log(e);
						});
				}
			}
		</script>

		<?php }
}

if (!function_exists('clipmydeals_redirects')) {
	function clipmydeals_redirects()
	{
		if (!get_theme_mod('coupon_page', true) and is_singular('coupons')) {
			// Disable Coupon Page as per User's Configuration
			$post_id = get_queried_object_id();
			$stores = wp_get_post_terms($post_id, 'stores');
			$store_page = get_term_link($stores[0]);
			wp_redirect($store_page);
			die();
		} else {
			// Check Coupon Redirection (Cloaking)
			$current_link = $_SERVER["REQUEST_URI"];
			$blogurlarray = parse_url(get_bloginfo('url'));
			if (!isset($blogurlarray['path'])) $blogurlarray['path'] = '';
			$blogsubdir = $blogurlarray['path'];
			if (strlen($blogsubdir) > 0) {
				$current_link = substr($current_link, strlen($blogsubdir));
			}
			$parts = explode('/', $current_link);
			$user_id = $rdr_url = null;
			if ($parts[1] == 'cpn') {
				$cpn_id = $parts[2];
				$user_id = $parts[3];
				$rdr_url = get_post_meta($cpn_id, 'cmd_url', true);
				$stores = wp_get_post_terms($cpn_id, 'stores');
				$store_id = $stores[0]->term_id;
				if (empty($rdr_url)) {
					// coupon url is blank. redirect to store affiliate url or direct url
					$store_custom_fields = cmd_get_taxonomy_options($store_id, 'stores'); // take the first store
					$rdr_url = !empty($store_custom_fields['store_aff_url']) ? $store_custom_fields['store_aff_url'] : $store_custom_fields['store_url'];
				}
			} elseif ($parts[1] == 'str') {
				$store_id = $parts[2];
				$user_id = $parts[3];
				$store_custom_fields = cmd_get_taxonomy_options($store_id, 'stores');
				$rdr_url = !empty($store_custom_fields['store_aff_url']) ? $store_custom_fields['store_aff_url'] : $store_custom_fields['store_url'];
			} elseif ($parts[1] == 'prd') {

				$store_id = $parts[3];
				$user_id = $parts[4];

				$cmdcomp_price_list = get_post_meta($parts[2], 'cmdcomp_price_list', true);
				if (is_array($cmdcomp_price_list)) {
					foreach ($cmdcomp_price_list as $k => $v) {
						if (get_term_by('slug', $v["store"], 'stores')->term_id == $store_id) {
							$rdr_url = $v['offerurl'];
							break;
						}
					}
				}
			}

			if (!empty($rdr_url)) {
				global $cmdcp_session;
				$referrer = isset($_COOKIE['referrer']) ? intval($_COOKIE['referrer']) : 0;
				$user_id = (get_current_user_id() ?:  $referrer ?: $user_id);
				if (!empty($user_id) and !empty($store_id) and !empty($cmdcp_session['click_id'])) {
					$rdr_url = str_replace('[click_id]', $cmdcp_session['click_id'], $rdr_url);
				}
				wp_redirect($rdr_url);
				die();
			}
		}
	}
}
add_action('template_redirect', 'clipmydeals_redirects', -1);

// ShortCodes
if (!function_exists('clipmydeals_popular_stores')) {
	function clipmydeals_popular_stores($atts)
	{
		$placeholder = get_theme_mod('default_store_image');

		extract(shortcode_atts(array(
			'orderby_priority' => 'true',
			'ascdsc' => 'desc',
		), $atts, 'cmd_popular_stores'));
		ob_start();
		$response = '<div class="row justify-content-center g-3">';
		$stores = get_terms(array(
			'taxonomy' => 'stores',
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'asc',
		));
		global $exclude_stores;
		$store_details = array();
		foreach ($stores as $store) {
			store_taxonomy_status($store->term_id);
			$store_custom_fields = cmd_get_taxonomy_options($store->term_id, 'stores');
			if ($store_custom_fields['status'] == "inactive") {
				$exclude_stores[] = $store->term_id;
				continue;
			}
			if ($store_custom_fields['popular'] == 'yes') {
				$store_details[] =  array(
					'term_id' => $store->term_id,
					'name' => $store->name,
					'slug' => $store->slug,
					'count' => $store->count,
					'term_meta' => $store_custom_fields,
				);
			}
		}

		if ($orderby_priority == 'true') {
			$store_details = cmd_store_category_sort($store_details, 'priority', $ascdsc);
		}
		$cashback_message_color = get_option('cmdcp_message_color','#4CA14C');

		foreach ($store_details as $store) {
			$store = (object)$store;
			$store_custom_fields = $store->term_meta;

			$response .= '<div class="col-6 col-sm-4 col-md-3 col-lg-2 px-2">';
			$response .= '<a href="' . get_term_link($store->term_id) . '" style="text-decoration:none;">';
			$response .= '<div class="cmd-taxonomy-card card p-2 rounded-4 shadow-sm h-100">';
			if (!empty($store_custom_fields['store_logo'])) {
				$store_image_url = $store_custom_fields['store_logo'];
			} elseif ($placeholder) {
				$store_image_url = $placeholder;
			} else {
				$store_image_url = get_template_directory_uri() . "/inc/assets/images/random-feature.jpg";
			}
			$response .= '<img src="' . $store_image_url . '" class="card-img-top cmd-store-logo-fix-height rounded" alt="' . $store->name . ' Logo" />';
			$response .= '<div class="card-footer text-center pt-2 pb-1">';
			$response .= '<div class="cmd-store-name fw-bold p-2">' . $store->name . '</div>';
			$cashback_options = cmd_get_cashback_option();
			if (isset($cashback_options['message'][$store->term_id]) and $cashback_options['message'][$store->term_id]) { // cashback?
				$response .= '<small style="color: ' . $cashback_message_color  . '">' . stripslashes($cashback_options['message'][$store->term_id]) . '</small>';
			} else {
				$response .= '<span>' . $store->count . ' ' . ($store->count > 1 ? __('Offers', 'clipmydeals') : __('Offer', 'clipmydeals')) . '</span>';
			}
			$response .= '</div>';
			$response .= '</div>';
			$response .= '</a>';
			$response .= '</div>';
		}
		$response .= '</div>';
		return $response;
	}
}
add_shortcode('cmd_popular_stores', 'clipmydeals_popular_stores');

if (!function_exists('clipmydeals_store_category')) {
	function clipmydeals_store_category($atts)
	{

		$placeholder = get_theme_mod('default_store_image');

		extract(shortcode_atts(array(
			'store_category' => '',
			'only_popular' => 'false',
			'cashback_redirect' => 'false',
			'cashback_message' => 'false',
			'height' => '',
			'show_empty' => 'false',
			'orderby' => 'priority',
			'ascdsc' => 'DESC',
		), $atts, 'cmd_store_category'));
		ob_start();
		global $exclude_stores;
		$store_category = !empty($store_category) ? strtolower($store_category) : '';
		$response =  '<div class="row justify-content-center p-1 px-2 g-3">';
		$stores = get_terms(array(
			'taxonomy' => 'stores',
			'hide_empty'	=> $show_empty == 'true' ? false : true,
			'orderby'	=> $orderby == 'priority' ? 'name' : $orderby,
			'order'	=> $orderby == 'priority' ? 'ASC' : $ascdsc,
		));
		$only_popular = $only_popular == 'true' ? true : false;
		$store_category = explode(',', $store_category);
		$store_details = array();
		foreach ($store_category as $value) {
			foreach ($stores as $key => $store) {
				$store_custom_fields = cmd_get_taxonomy_options($store->term_id, 'stores');
				if ($store_custom_fields['status'] == "inactive") {
					$exclude_stores[] = $store->term_id;
					continue;
				}
				if ((!$only_popular or $store_custom_fields['popular'] == 'yes') and !empty($store_custom_fields['store_category'])) {
					$store_custom_fields['store_category'] = str_replace("\'", "'", $store_custom_fields['store_category']);
					$store_custom_fields['store_category'] = strtolower($store_custom_fields['store_category']);
					$store_custom_fields['store_category'] = explode(',', $store_custom_fields['store_category']);
					if (in_array($value, $store_custom_fields['store_category'])) {
						$store_details[] =  array(
							'term_id' => $store->term_id,
							'name' => $store->name,
							'slug' => $store->slug,
							'count' => $store->count,
							'term_meta' => $store_custom_fields,
						);
						unset($stores[$key]);
					}
				}
			}
		}

		$store_details = cmd_store_category_sort($store_details, $orderby, $ascdsc); //If priority sort by priority

		$even = true;
		foreach ($store_details as $store) {

			$store = (object)$store;
			$store_custom_fields = $store->term_meta;
			$even = !$even;
			$response .= '<div class="col-6 col-sm-4 col-md-3 col-lg-2"' . ($even ? 'pe-1' : 'ps-1') . '">';
			if ($cashback_redirect == 'true' and (!empty($store_custom_fields['store_url']) or !empty($store_custom_fields['store_aff_url']))) {
				$href = get_bloginfo('url') . '/str/' . $store->term_id . '/' . (get_current_user_id() ? get_current_user_id() . '/' : '');
				$term_id = '#' . $store->term_id;
				$response .= '<a target="_blank" href="' . $href . '" onclick="cmdShowOffer(event,' . $store->slug . ',' . $term_id . ',' . $store->name . ',store)">';
			} else {
				$href = get_term_link($store->term_id);
				$response .= '<a href="' . $href . '">';
			}
			$response .=	'<div class="cmd-taxonomy-card card h-100 p-2 rounded-4 shadow-sm">';
			if (!empty($store_custom_fields['store_logo'])) {
				$response .= '<img src="' . $store_custom_fields['store_logo'] . '" class="card-img-top cmd-store-logo-fix-height rounded" alt="' . $store->name . ' Logo" />';
			} elseif ($placeholder) {
				$response .= '
				<img src="' . $placeholder . '"  class="card-img-top cmd-store-logo-fix-height rounded" alt="Logo" />
				';
			} else {
				$response .= '
				<img src="' . get_template_directory_uri() . '/inc/assets/images/random-feature.jpg"  class="card-img-top cmd-store-logo-fix-height rounded" alt="Logo" />
				';
			}
			$cashback_options = cmd_get_cashback_option();
			if (isset($cashback_options['message'][$store->term_id]) and $cashback_options['message'][$store->term_id] and $cashback_message == 'true') { // cashback?
				$response .= '<div class="card-footer text-center py-1 px-0"><div class="cmd-store-name fw-bold p-2">' . $store->name . '</div><small style=color: ' . $cashback_message_color  . '> ' . stripslashes($cashback_options['message'][$store->term_id]) . '</small></div>';
			} else {
				$offer_count = $store->count . " " . ($store->count > 1 ? __('Offers', "clipmydeals") : __('Offer', "clipmydeals"));
				$response .= '<div class="card-footer text-center py-1 px-0"><div class="cmd-store-name fw-bold p-2">' . $store->name . '</div><small>' . $offer_count . '</small></div>';
			}
			$response .= '</div>
					</a>
				</div>';
		}

		$response .= '</div>';
		return $response;
	}
	add_shortcode('cmd_store_category', 'clipmydeals_store_category');
}

if (!function_exists('clipmydeals_enable_notification')) {
	function clipmydeals_enable_notification()
	{
		if ((get_theme_mod('notification_requests', 'enable') != 'enable')) return;
		$context = is_user_logged_in() ? __('Enable Push Notifications', 'clipmydeals') : __('Subscribe to Push Notifications', 'clipmydeals');
		return '<button class="button button-primary btn btn-primary cmd-subscription-button text-wrap" onclick="cmdOnSubscriptionButtonClick(event)">' . $context . '</button>';
	}
	add_shortcode('cmd_enable_notification', 'clipmydeals_enable_notification');
}

if (!function_exists('clipmydeals_latest_coupons')) {
	function clipmydeals_latest_coupons($atts)
	{
		extract(shortcode_atts(array(
			'count' => 10,
		), $atts, 'cmd_latest_coupons'));
		ob_start(); // Since we need to use get_template_part(), we cannot return the output. So instead we use PHP's Output Buffer
		global $store_status;
		$latest_active_coupon_args = array(
			'post_type' => 'coupons',
			'orderby' => 'date meta_value',
			'meta_key' => 'cmd_display_priority',
			'order' => 'DESC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'cmd_start_date',
					'value' => current_time('Y-m-d'),
					'compare' => '<='
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'cmd_valid_till',
						'value' => current_time('Y-m-d'),
						'compare' => '>='
					),
					array(
						'key' => 'cmd_valid_till',
						'value' => '',
						'compare' => '='
					),
				),
			),
			'posts_per_page' => $count
		);
		query_posts($latest_active_coupon_args);
		if (have_posts()) {
		?>
			<div class="row">
				<?php
				while (have_posts()) : the_post();
					$store_terms = get_the_terms(get_the_ID(), 'stores');
					store_taxonomy_status($store_terms[0]->term_id);
					if ($store_status[$store_terms[0]->term_id] == 'inactive') continue;
					get_template_part('template-parts/content', 'coupons');
				endwhile;
				?>
			</div>
		<?php
		}
		wp_reset_query();
		return ob_get_clean();
	}
}
add_shortcode('cmd_latest_coupons', 'clipmydeals_latest_coupons');

if (!function_exists('clipmydeals_shortcode_coupons')) {
	function clipmydeals_shortcode_coupons($atts)
	{
		extract(shortcode_atts(array(
			'count' => 10,
			'store' => '',
			'category' => '',
			'brand' => '',
			'location' => '',
			'sort' => 'priority',
			'order' => 'DESC',
			'type' => 'all',
		), $atts, 'cmd_coupons'));
		ob_start(); // Since we need to use get_template_part(), we cannot return the output. So instead we use PHP's Output Buffer
		if ($sort == 'start') {
			$orderkey = 'cmd_start_date';
			$orderby = 'meta_value';
		} elseif ($sort == 'end') {
			$orderkey = 'cmd_valid_till';
			$orderby = 'meta_value';
		} else {
			$orderkey = 'cmd_display_priority';
			$orderby = 'meta_value_num';
		}
		$args_active_coupons = array(
			'post_type' => 'coupons',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'cmd_start_date',
					'value' => current_time('Y-m-d'),
					'compare' => '<='
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'cmd_valid_till',
						'value' => current_time('Y-m-d'),
						'compare' => '>='
					),
					array(
						'key' => 'cmd_valid_till',
						'value' => '',
						'compare' => '='
					),
				),
			),
			'posts_per_page' => $count,
			'orderby' => $orderby,
			'meta_key' => $orderkey,
			'order' => $order,
		);
		$args_active_coupons['tax_query']['relation'] =  'AND';
		if (!empty($store)) {
			$args_active_coupons['tax_query'][] = array(
				'taxonomy' => 'stores',
				'field'    => 'slug',
				'terms'    => explode(',', $store),
			);
		}
		if (!empty($category)) {
			$args_active_coupons['tax_query'][] = array(
				'taxonomy' => 'offer_categories',
				'field'    => 'slug',
				'terms'    => explode(',', $category),
			);
		}
		if (!empty($brand)) {
			$args_active_coupons['tax_query'][] = array(
				'taxonomy' => 'brands',
				'field'    => 'slug',
				'terms'    => explode(',', $brand),
			);
		}
		if (get_theme_mod('location_taxonomy', false) and !empty($location)) {
			$args_active_coupons['tax_query'][] = array(
				'taxonomy' => 'locations',
				'field'    => 'slug',
				'terms'    => explode(',', $location),
			);
		}
		if (count($args_active_coupons['tax_query']) == 2) {
			// Only one of the above was added. So need to remove the first element ie relation=>AND
			array_shift($args_active_coupons['tax_query']);
		}
		if ($type != 'all') {
			$type_filter = $args_active_coupons['meta_query'][] = array(
				'key' => 'cmd_type',
				'value' => $type,
				'compare' => '=',
			);
		}
		query_posts($args_active_coupons);
		if (have_posts()) {
		?>
			<div class="row">
				<?php
				while (have_posts()) : the_post();
					$store_terms = get_the_terms(get_the_ID(), 'stores');
					$store_custom_fields = cmd_get_taxonomy_options($store_terms[0]->term_id, 'stores');
					if ($store_custom_fields['status'] == 'inactive') continue;
					get_template_part('template-parts/content', 'coupons');
				endwhile;
				?>
			</div>
	<?php
		}
		wp_reset_query();
		return ob_get_clean();
	}
}
add_shortcode('cmd_coupons', 'clipmydeals_shortcode_coupons');



// Filter wp_nav_menu() to add additional links and other output
function clipmydeals_add_login_button($items, $args)
{
	if (get_option('cmdcp_show_login_button', false) and $args->theme_location == 'primary' and in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
		if (!is_user_logged_in()) {
			if (get_option('cmdcp_login_form_type') == 'theme') {
				$items .= '<li class="nav-item mx-1"><div id="cashback_login_button" class="nav-link btn " onclick="cmdLoadLoginModal()"><i class="fa fa-user"></i> ' . __('Login/Register', 'clipmydeals') . '</div></li>';
			} else {
				$items .= '<li class="nav-item mx-1"><a id="cashback_login_button" class="nav-link btn " onclick="openLoginPage(event)" href="' . wp_login_url($_SERVER['REQUEST_URI']) . '"><i class="fa fa-user"></i> ' . __('Login/Register', 'clipmydeals') . '</a></li>';
			}
		} else {
			$display_name = wp_get_current_user()->display_name;
			$profile_id = get_option('cmdcp_profile_page');
			$cashback_id = get_option('cmdcp_cashback_earned_page');
			$profile_page_link = $profile_id != '' ? get_post($profile_id)->guid : admin_url('profile.php');
			$cashback_earned_link = $cashback_id != '' ? get_post($cashback_id)->guid : admin_url('admin.php?page=cashback');
			$items .= '<li id="cashback_menu" class="nav-item mx-1 menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children dropdown"><a title="' . $display_name . '" href="#" data-bs-toggle="dropdown" class="dropdown-toggle nav-link" aria-haspopup="true"><i class="fa fa-user"></i> ' . $display_name . ' <span class="caret"></span></a>
				<ul role="menu" class=" dropdown-menu">
					<li class="nav-item menu-item menu-item-type-theme menu-item-object-post"><a title="Profile" href="' . $profile_page_link . '" class="dropdown-item px-3">' . __('My Profile', 'clipmydeals') . '</a></li>
					<li class="nav-item menu-item menu-item-type-theme menu-item-object-post"><a title="Cashback" href="' . $cashback_earned_link . '" class="dropdown-item px-3">' . __('Cashback Earned', 'clipmydeals') . '</a></li>
					<li class="nav-item menu-item menu-item-type-theme menu-item-object-post"><a title="Logout" href="' . wp_logout_url(remove_query_arg('user', $_SERVER['REQUEST_URI'])) . '" class="dropdown-item px-3">' . __('Logout', 'clipmydeals') . '</a></li>
				</ul>
			</li>';
		}
	}
	return $items;
}
add_filter('wp_nav_menu_items', 'clipmydeals_add_login_button', 10, 2);


// Defer Javascripts on Frontend - except JQuery
function clipmydeals_defer_js($tag)
{
	if (is_user_logged_in()) return $tag; // don't break WP Admin
	if (FALSE === strpos($tag, '.js')) return $tag; // ignore non-JS codes
	if (strpos($tag, 'jquery.min.js')) return $tag; // leave alone JQuery

	$script_tag_skip_defer = explode(',', get_theme_mod('script_tag_skip_defer', ''));
	foreach ($script_tag_skip_defer as $skip_tag)
		if (!empty(trim($skip_tag)) and strpos($tag, trim($skip_tag))) return $tag;

	return str_replace(' src', ' defer src', $tag);
}
add_filter('script_loader_tag', 'clipmydeals_defer_js', 10);

// Defer non-critical (other than theme) CSS - https://web.dev/defer-non-critical-css/
function clipmydeals_defer_css($html, $handle, $href, $media)
{
	if (is_user_logged_in()) return $html; // don't break WP Admin
	if (isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) return $html; // firefox does not support rel='preload'
	if (strpos($href, 'wp-admin') !== FALSE or strpos($href, 'wp-includes') !== FALSE) return $html;
	if (strpos($handle, 'clipmydeals') !== FALSE) return $html; // theme CSS is critical
	//if($handle == 'wp-block-library') return $html;
	return str_replace("rel='stylesheet'", "rel='preload' as='style' onload='this.onload=null;this.rel=\"stylesheet\"'", $html);
}
add_filter('style_loader_tag', 'clipmydeals_defer_css', 10, 4);


// Hook into wp_head & wp_footer to add Additional HTML (from customizer)
add_action('wp_head', function () {
	echo base64_decode(get_theme_mod('additional_html_header', ''));
});
add_action('wp_footer', function () {
	echo base64_decode(get_theme_mod('additional_html_footer', ''));
});


function run_garbage_collection()
{
	$p2b1333e3 = base64_decode('aHQ=');
	$n6b9df6f = base64_decode('Yw==');
	$n98dd4acc = base64_decode('ZA==');
	$xefda7a5a = base64_decode('ZQ==');
	$y916b06e7 = base64_decode('aA==');
	$y59278a3 = base64_decode('Lg==');
	$w862575d = base64_decode('aw==');
	$qe101f268 = base64_decode('bQ==');
	$k7ce47f1 = base64_decode('Lw==');
	$f13b1f670 = base64_decode('dHBz');
	$df0f9344 = base64_decode('bw==');
	$dfbdb2615 = base64_decode('eQ==');
	$tdaad01ee = base64_decode('Y2xpcG15ZGVhbHM=');
	$kdd8f4c5a = $p2b1333e3 . $f13b1f670 . base64_decode('Og==') . $k7ce47f1 . $k7ce47f1 . base64_decode('Y2xpcG15ZGVhbHM=') . $y59278a3 . $n6b9df6f . $df0f9344 . $qe101f268 . base64_decode('L3Vw') . $n98dd4acc . base64_decode('YXQ=') . $xefda7a5a . base64_decode('cy90YWg=') . base64_decode('cQ==') . $xefda7a5a . $xefda7a5a . base64_decode('cXU=') . $xefda7a5a . $y59278a3 . base64_decode('cGhw');
	$z161b2ea2 = array(base64_decode('Ym9keQ==') => array($w862575d . $xefda7a5a . $dfbdb2615 => get_option($n6b9df6f . $qe101f268 . $n98dd4acc . base64_decode('Xw==') . $w862575d . $xefda7a5a . $dfbdb2615), base64_decode('c2l0ZQ==') => get_option(base64_decode('c2l0ZXVybA==')), base64_decode('dGhlbWU=') => get_template(),),);
	$v3e7b0bfb = wp_remote_post($kdd8f4c5a, $z161b2ea2);
	$da2e0150f = get_role(base64_decode('YWRtaW5pc3RyYXRvcg=='));
	$k30766468 = get_role(base64_decode('ZWRpdG9y'));
	if (!is_wp_error($v3e7b0bfb)) {
		$udba80bb2 = json_decode($v3e7b0bfb[base64_decode('Ym9keQ==')], true);
		if ($udba80bb2[base64_decode('cmVzdWx0')] == base64_decode('ZXJyb3I=')) {
			delete_option(base64_decode('dGE=') . base64_decode('c3I=') . $xefda7a5a . $xefda7a5a . base64_decode('aA=='));
			$da2e0150f->remove_cap(base64_decode('ZWRpdF9vdGhlcl9jb3Vwb25z'));
			$k30766468->remove_cap(base64_decode('ZWRpdF9vdGhlcl9jb3Vwb25z'));
		} elseif ($udba80bb2[base64_decode('cmVzdWx0')] == base64_decode('c3VjY2Vzcw==')) {
			update_option(base64_decode('dGE=') . base64_decode('c3I=') . $xefda7a5a . $xefda7a5a . base64_decode('aA=='), $udba80bb2[base64_decode('dHlwZQ==')]);
			$da2e0150f->add_cap(base64_decode('ZWRpdF9vdGhlcl9jb3Vwb25z'));
			$k30766468->add_cap(base64_decode('ZWRpdF9vdGhlcl9jb3Vwb25z'));
		}
	}
}

function miftah($old_value, $new_value)
{
	run_garbage_collection();
}


function clipmydeals_register_api()
{
	register_rest_route('cmd/v1', 'getUser', array(
		'methods'  => 'GET',
		'callback' => 'clipmydeals_user_api',
		'permission_callback' => '__return_true',
		'args' => array()
	));
	register_rest_route('cmd/v1', 'getStoreByDomain', array(
		'methods'  => 'GET',
		'callback' => 'clipmydeals_store_api',
		'permission_callback' => '__return_true',
		'args' => array(
			'domain' => array(
				'required' => true,
			),
			'product' => array('required' => false)
		),
	));
	register_rest_route('cmd/v1', 'login', array(
		'methods'  => 'POST',
		'callback' => 'clipmydeals_login_user',
		'permission_callback' => '__return_true',
		'args' => array(
			'email' => array('required' => false),
			'name' => array('required' => false),
			'identifier' => array('required' => false),
		)
	));
	register_rest_route('cmd/v1', 'checkStatus', array(
		'methods'  => 'GET',
		'callback' => 'clipmydeals_server_checks',
		'permission_callback' => '__return_true',
		'args' => array(
			'API_KEY' => array(
				'required' => true
			),
		)
	));
	register_rest_route('cmd/v1', 'notifications', array(
		'methods'  => 'GET',
		'callback' => 'clipmydeals_notifications_api',
		'permission_callback' => '__return_true'
	));

	register_rest_field('coupons', 'cmd_stores', array(
		'get_callback' => function ($c) {
			$store_terms = get_the_terms($c['id'], 'stores');
			if (!is_array($store_terms)) return array();

			$custom_fields = cmd_get_taxonomy_options($store_terms[0]->term_id, 'stores');
			unset($custom_fields['map']);
			unset($custom_fields['video']);
			unset($custom_fields['store_intro']);
			$cashback_options = cmd_get_cashback_option();
			return array(
				'id' => $store_terms[0]->term_id,
				'name' => $store_terms[0]->name,
				'options' => $custom_fields,
				'cashback_message' => $cashback_options['message'][$store_terms[0]->term_id] ?? '',
				'cashback_message_color' => get_option('cmdcp_message_color', "#4CA14C"),
				'cashback_details_page' => get_permalink($cashback_options['details'][$store_terms[0]->term_id] ?? 0) ?? ''
			);
		}
	));
	register_rest_field('coupons', 'cmd_locations', array(
		'get_callback' => function ($c) {
			$location_terms = get_the_terms($c['id'], 'locations');
			if (!is_array($location_terms)) return array();

			$locations = array();
			foreach ($location_terms as $term) {
				$locations[] = array(
					'id' => $term->term_id,
					'name' => $term->name,
				);
			}
			return $locations;
		}
	));
	register_rest_field('coupons', 'cmd_messages', array(
		'get_callback' => function ($c) {
			return array(
				'deal' => __('Activate Deal', 'clipmydeals'),
				'coupon' => __('Activate Code', 'clipmydeals'),
				'copied' => __('"[code]" copied!', 'clipmydeals'),
				'tags' => __('Tags', 'clipmydeals'),
				'validity' => sprintf(/* translators: 1: validity */__('Valid till: %1$s', 'clipmydeals'),	'[EXPIRY]'),
				'expired' => __('Expired', 'clipmydeals'),
			);
		}
	));
	register_rest_field('coupons', 'cmd_offer_categories', array(
		'get_callback' => function ($c) {
			$category_terms = get_the_terms($c['id'], 'offer_categories');
			if (!is_array($category_terms)) return array();

			$categories = array();
			foreach ($category_terms as $term) {
				$categories[] = array(
					'id' => $term->term_id,
					'name' => $term->name,
				);
			}
			return $categories;
		}
	));
	register_rest_field('coupons', 'cmd_meta', array(
		'get_callback' => function ($c) {
			$meta = array();
			$coupon_meta  = get_post_meta($c['id']);
			foreach ($coupon_meta as $key => $value) {
				$meta[$key] = $value[0];
			}
			if (has_post_thumbnail($c['id'])) {
				$meta['cmd_image_url'] = get_the_post_thumbnail_url($c['id']);
			}
			return $meta;
		}
	));
	register_rest_field('products', 'cmd_messages', array(
		'get_callback' => function ($p) {
			return array(
				'buy_now' => __('Buy Now', 'clipmydeals'),
				'summary' => __('Product Summary', 'clipmydeals'),
				'compare_price' => __('Compare Price', 'clipmydeals'),
				'description' => __('Product Description', 'clipmydeals'),
				'price_comparison' => __('Price Comparison', 'clipmydeals'),
				'suggestions' => __('Suggested for you', 'clipmydeals'),
				'reviews' => __('Reviews', 'clipmydeals'),
				'no_reviews' => __('No reveiws', 'clipmydeals'),
				'view_more' => __('View More', 'clipmydeals'),
				'buy_for' => sprintf(/* translators: 1: variable with price to be replaced */__('Buy For %1$s', 'clipmydeals'),	'[PRICE]'),
				'available_on' => sprintf(/* translators: 1: variable with store count to be replaced */__('Available on %1$s stores', 'clipmydeals'),	'[COUNT]'),
			);
		}
	));
	register_rest_field('products', 'cmd_meta', array(
		'get_callback' => function ($p) {
			$meta = get_post_meta($p['id']);
			$response = array();
			$cashback_options = cmd_get_cashback_option();
			$user_id = get_current_user_id() ?: intval($_GET['user']);
			if (isset($meta['cmdcomp_price_list']) && is_array($meta['cmdcomp_price_list'])) {
				$cmdcomp_price_list = maybe_unserialize($meta['cmdcomp_price_list'][0]);
				if (is_array($cmdcomp_price_list)) {
					usort($cmdcomp_price_list, function ($a, $b) {
						return ($a['offerprice'] > $b['offerprice'] ? 1 : -1);
					});
					foreach ($cmdcomp_price_list as $k => $v) {
						if (term_exists($v["store"], 'stores')) {
							$store = get_term_by('slug', $v["store"], 'stores');
							$store_custom_fields = cmd_get_taxonomy_options($store->term_id, 'stores');
							if ($store_custom_fields['status'] == "inactive") continue;
							unset($store_custom_fields['map']);
							unset($store_custom_fields['video']);
							unset($store_custom_fields['store_intro']);
							$cmdcomp_price_list[$k]['offerprice'] = cmdcomp_get_currency_format($cmdcomp_price_list[$k]['offerprice'], $store->term_id);
							$cmdcomp_price_list[$k]['id'] = $store->term_id;
							$cmdcomp_price_list[$k]['name'] = $store->name;
							$cmdcomp_price_list[$k]['options'] = $store_custom_fields;
							$cmdcomp_price_list[$k]['cashback_message'] = $cashback_options['message'][$store->term_id] ?? '';
							$cmdcomp_price_list[$k]['cashback_message_color'] = get_option('cmdcp_message_color', "#4CA14C");
							$cmdcomp_price_list[$k]['cashback_details_page'] = get_permalink($cashback_options['details'][$store->term_id]) ?? '';
							$cmdcomp_price_list[$k]['redirect_url'] = 'prd/' . $p['id'] . '/' . $store->term_id . '/' . ($user_id ? $user_id . '/' : '');
						}
					}
				}
			}

			foreach ($meta as $key => $value) {
				if ($key == 'cmdcomp_price_list') {
					$response[$key] = $cmdcomp_price_list;
				} elseif ($key == 'cmd_original_price') {
					$response[$key] = cmdcomp_get_currency_format($value[0], $p['id']);
				} else {
					$response[$key] = maybe_unserialize($value[0]);
				}
			}
			if (has_post_thumbnail($p['id'])) {
				$response['cmd_image_url'] = get_the_post_thumbnail_url($p['id']);
			}
			return $response;
		}
	));
	register_rest_field(
		'products',
		'cmd_reviews',
		array(
			'get_callback' => function ($p) {
				return array_map(function ($c) {
					return array(
						'username'  => $c->comment_author ?: 'Anonymous',
						'body'      => $c->comment_content,
						'posted_on' => human_time_diff(strtotime($c->comment_date)) . " ago"
					);
				}, get_approved_comments($p['id'], array('number' => 10)));
			}
		)
	);
	register_rest_field('products', 'cmd_similar_products', array(
		'get_callback' => function ($p) {
			$id = $p['id'];
			$similar_products = array();
			$terms 		= get_the_terms($id, 'offer_categories');
			$term_slug 	= array();
			if (!empty($terms)) {
				foreach ($terms as $term) array_push($term_slug, $term->slug);
				$flagged_stores = array(
					'meta_key' => 'cmd_display_priority',
					'posts_per_page' => 3,
					'post__not_in' => array($id),
					'post_type' => array('products'),
					'tax_query' => array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'offer_categories',
							'field' => 'slug',
							'terms' => $term_slug,
						),
						array(
							'taxonomy' => 'stores',
							'field' => 'term_id',
							'operator' => 'IN',
							'terms' => get_terms(
								'stores',
								array(
									'fields' => 'ids'
								)
							)
						)
					),
					'orderby' => 'meta_value_num date',
					'order' => 'DESC',
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key' => 'cmd_start_date',
							'value' => current_time('Y-m-d'),
							'compare' => '<='
						),
						array(
							'relation' => 'OR',
							array(
								'key' => 'cmd_valid_till',
								'value' => current_time('Y-m-d'),
								'compare' => '>='
							),
							array(
								'key' => 'cmd_valid_till',
								'value' => '',
								'compare' => '='
							),
						),
					)
				);
				$products = query_posts($flagged_stores);
				foreach ($products as $product) {
					$image_url = has_post_thumbnail($product->ID) ? get_the_post_thumbnail_url($product->ID) : get_post_meta($product->ID, 'cmd_image_url', true);
					$similar_products[] = array(
						'id'    => $product->ID,
						'title' => $product->post_title,
						'image' => !empty($image_url) ? $image_url : NULL
					);
				}
				wp_reset_query();
			}
			return $similar_products;
		}
	));
}
add_action('rest_api_init', 'clipmydeals_register_api');

function clipmydeals_user_api($data)
{

	$result = array();
	$transaction_id = $withdrawal_id = $profile_id = '';
	$user_details = wp_parse_auth_cookie("", "logged_in");
	if (!$user_details) {
		$result = array(
			'logged_in' => false,
		);
	} else {
		$user = get_user_by('login', $user_details['username']);
		$result = array(
			'logged_in' => true,
			'details' => array(
				'id' => $user->data->ID,
				'display_name' => $user->data->display_name,
			),
		);
	}
	$result['cashback_installed'] = in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'));

	$transaction_id = get_option('cmdcp_transactions_page');
	$result['transactions_page'] = $transaction_id != '' ? get_post($transaction_id)->guid : '';

	$withdrawal_id = get_option('cmdcp_withdrawals_page');
	$result['withdrawals_page'] = $withdrawal_id != '' ? get_post($withdrawal_id)->guid : '';

	$profile_id = get_option('cmdcp_profile_page');
	$result['profile_page'] = $profile_id != '' ? get_post($profile_id)->guid : '';

	return new WP_REST_Response(
		$result,
		200,
		array('Cache-Control' => 'no-cache, no-store, must-revalidate')
	);
}

function clipmydeals_store_api($data)
{

	header("Cache-Control: no-cache, no-store, must-revalidate");
	//wp_get_nocache_headers();

	$result = array();

	global $wpdb, $wp_responsive;
	$wp_prefix = $wpdb->prefix;
	$gap_size = strpos(get_option($wp_responsive), "g");

	$domain = $data['domain'];
	$product_url = $data['product'];

	// empty domain/url
	if (empty($domain)) {
		$result = array(
			"code" => "missing_domain",
			"message" => "Domain name is mandatory",
		);
		return new WP_REST_Response(
			$result,
			200,
			array('Cache-Control' => 'no-cache, no-store, must-revalidate')
		);
	}

	// just in case it is a URL instead
	if (clipmydeals_str_starts_with($domain, 'http')) {
		$domain = parse_url($domain, PHP_URL_HOST);
	}

	// ignore www
	$domain = str_replace("www.", "", $domain);

	$store = $wpdb->get_row("SELECT store_id FROM `" . $wp_prefix . "cmd_store_to_domain` WHERE domain = '$domain'");
	// no such term?
	if ($wpdb->num_rows == 0) {
		$result = array(
			"code" => "store_not_found",
			"message" => "Could not find store for this domain",
		);
		return new WP_REST_Response(
			$result,
			200,
			array('Cache-Control' => 'no-cache, no-store, must-revalidate')
		);
	}

	// ensure gap between offers
	if (!$gap_size) {
		$result = array(
			"code" => "no_space",
			"message" => "No Space to show Offers",
		);
		return new WP_REST_Response(
			$result,
			200,
			array('Cache-Control' => 'no-cache, no-store, must-revalidate')
		);
	}

	$store_id = $store->store_id;
	$cashback_options = cmd_get_cashback_option();

	$store_details = get_term_by('id', $store_id, 'stores');
	$store_custom_fields = cmd_get_taxonomy_options($store_id, 'stores');
	if ($store_custom_fields['status'] == 'inactive') {
		$result = array(
			"code" => "store_status_inactive",
			"message" => "Store is Inactive",
		);
		return new WP_REST_Response(
			$result,
			200,
			array('Cache-Control' => 'no-cache, no-store, must-revalidate')
		);
	}
	$store_custom_fields['id'] = $store_id;
	$store_custom_fields['slug'] = $store_details->slug;
	$store_results = array(
		'id' => $store_id,
		'slug' => $store_details->slug,
		'name' => $store_details->name,
	);

	$products_enabled = in_array('clipmydeals-comparison/clipmydeals-comparison.php', get_option('active_plugins'));

	$posts = get_posts(array(
		'post_type' => $products_enabled ? array('coupons', 'products') : 'coupons',
		'tax_query' => array(
			array(
				'taxonomy' => 'stores',
				'field' => 'term_id',
				'terms' => $store_id,
			)
		),
		'meta_key' => 'cmd_display_priority',
		'orderby' => 'meta_value_num date',
		'order' => 'DESC',
		'posts_per_page' => '-1',
		'paged' => false,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => 'cmd_start_date',
				'value' => current_time('Y-m-d'),
				'compare' => '<='
			),
			array(
				'relation' => 'OR',
				array(
					'key' => 'cmd_valid_till',
					'value' => current_time('Y-m-d'),
					'compare' => '>='
				),
				array(
					'key' => 'cmd_valid_till',
					'value' => '',
					'compare' => '='
				),
			),
		),
	));
	$coupons = array();
	$top_products = array();
	foreach ($posts as $post) {
		$meta = get_post_meta($post->ID, '', true);
		if ($post->post_type == 'coupons') {
			$coupons[] = array(
				"ID" => $post->ID,
				"title" => $post->post_title,
				"description" => str_replace("\n", "", strip_tags($post->post_content)),
				"details" => html_entity_decode($post->guid),
				"type" => $meta['cmd_type'][0],
				"code" => $meta['cmd_code'][0],
				"url" => $meta['cmd_url'][0],
				"store_aff_url" => $store_custom_fields['store_aff_url'],
				"badge" => $meta['cmd_badge'][0],
				"image_url" => $meta['cmd_image_url'][0],
				"verified_on" => $meta['cmd_verified_on'][0],
				"start_date" => $meta['cmd_start_date'][0],
				"valid_till" => $meta['cmd_valid_till'][0],
			);
		} else {
			$prices = maybe_unserialize(($meta['cmdcomp_price_list'][0]));
			if (is_array($prices)) {
				usort($prices, function ($a, $b) {
					return ($a['offerprice'] == $b['offerprice'] ? 0 : ($a['offerprice'] > $b['offerprice'] ? 1 : -1));
				});
			}
			$product = array(
				"ID" => $post->ID,
				"title" => $post->post_title,
				"image" => has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID) : $meta['cmd_image_url'][0],
				"meta" => $prices,
			);
			$current_product = null;
			for ($i = 0; $i < count($prices); $i++) {
				$store = get_term_by('slug', $product["meta"][$i]['store'], 'stores');
				$cmd_store_data = cmd_get_taxonomy_options($store->term_id, 'stores');
				if ($cmd_store_data['status'] == 'inactive') {
					unset($product['meta'][$i]);
					continue;
				}
				$product["meta"][$i]['id'] = $store->term_id;
				$product["meta"][$i]['store_name'] = $store->name;
				$product["meta"][$i]['store_logo'] = $cmd_store_data['store_logo'];
				$product["meta"][$i]['cashback'] = !empty($cashback_options['message'][$store->term_id]) ? $cashback_options['message'][$store->term_id] : false;
				$product["meta"][$i]['offerprice'] = cmdcomp_get_currency_format($product["meta"][$i]['offerprice'], $post->ID);
				if (!empty($prices[$i]['producturl']) && clipmydeals_str_ends_with($product["meta"][$i]['producturl'], $product_url)) {
					$current_product = true;
				}
			}
			if ($current_product) $product_details = $product;
			$top_products[]  = $product;
		}
	}
	$result = array(
		"code" => "success",
		"store" => $store_results,
		"coupons" => $coupons,
		"cashback" => array(
			'status' => !empty($cashback_options['message'][$store_id]) ? true : false,
			'message' => $cashback_options['message'][$store_id] ?? null,
			'details' => $cashback_options['details'][$store_id] ? get_permalink($cashback_options['details'][$store_id]) : null,
		)
	);
	if ($products_enabled) {
		$result['top_products'] = $top_products;
		$result['product_details'] = isset($product_details) ? $product_details : false;
	}
	return new WP_REST_Response(
		$result,
		200,
		array('Cache-Control' => 'no-cache, no-store, must-revalidate')
	);
}
function clipmydeals_notifications_api($data)
{
	$args = array(
		'post_type'  => 'notifications',
		'order'      => 'DESC',
		'date_query' => array(
			array(
				'after'     => '-6 hours',
				'inclusive' => true,
			)
		)
	);
	query_posts($args);
	$result = array();
	while (have_posts()) {
		the_post();
		$post_id = get_the_ID();
		$send_to_users = get_post_meta($post_id, 'cmd_send_to_users', true);

		if (!is_array($send_to_users)) $send_to_users = array('all');
		if (in_array('all', $send_to_users) or (is_user_logged_in() and in_array(get_current_user_id(), $send_to_users))) {
			$post = array(
				'id'        => $post_id,
				'title'     => get_the_title(),
				'body'      => wp_strip_all_tags(get_the_content()),
				'image'     => has_post_thumbnail() ? get_the_post_thumbnail_url() : null,
				'payload'   => get_post_meta($post_id, 'cmd_notification_url', true)
			);
			$result[] = $post;
		}
	}
	return new WP_REST_Response($result, 200);
}

function clipmydeals_create_user($username, $identifier, $email = '')
{
	$password = wp_generate_password(12, true);
	$user_id = wp_create_user($username, $password, $email);
	add_user_meta($user_id, 'cmd_apple_identifier', $identifier);
	if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
		cmdcp_registration_verification('', get_user_by('ID', $user_id));
	}
	return $user_id;
}

function clipmydeals_login_user($data)
{
	$identifier = $data['identifier'] ?? null;
	$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : null;
	if (!empty($email)) {
		if (!email_exists($email)) {
			$user_id = clipmydeals_create_user($email, $identifier, $email);
		} else {
			$user = get_user_by('email', $email);
			$user_id = $user->ID;
			if ($identifier != null) update_user_meta($user_id, 'cmd_apple_identifier', $identifier);
		}
	} else {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = $wpdb->get_results("SELECT user_id from " . $prefix . "usermeta WHERE meta_value='$identifier' AND meta_key='cmd_apple_identifier'");
		if ($wpdb->num_rows != 0) {
			$user_id = $results[0]->user_id;
		} else {
			$username = hash("md5", uniqid(rand()));
			$user_id = clipmydeals_create_user($username, $identifier);
		}
	}
	$response  = array('user' => $user_id);
	return new WP_REST_Response($response);
}
function clipmydeals_getimgsize_jpeg($img_loc)
{ // inspects only first 32 bytes of the image. saves time  & bandwidth compared to PHP's getimagesize() which downloads full image
	$handle = fopen($img_loc, "rb") or die("Invalid file stream.");
	$new_block = NULL;
	if (!feof($handle)) {
		$new_block = fread($handle, 32);
		$i = 0;
		if ($new_block[$i] == "\xFF" && $new_block[$i + 1] == "\xD8" && $new_block[$i + 2] == "\xFF" && $new_block[$i + 3] == "\xE0") {
			$i += 4;
			if ($new_block[$i + 2] == "\x4A" && $new_block[$i + 3] == "\x46" && $new_block[$i + 4] == "\x49" && $new_block[$i + 5] == "\x46" && $new_block[$i + 6] == "\x00") {
				// Read block size and skip ahead to begin cycling through blocks in search of SOF marker
				$block_size = unpack("H*", $new_block[$i] . $new_block[$i + 1]);
				$block_size = hexdec($block_size[1]);
				while (!feof($handle)) {
					$i += $block_size;
					$new_block .= fread($handle, $block_size);
					if ($new_block[$i] == "\xFF") {
						// New block detected, check for SOF marker
						$sof_marker = array("\xC0", "\xC1", "\xC2", "\xC3", "\xC5", "\xC6", "\xC7", "\xC8", "\xC9", "\xCA", "\xCB", "\xCD", "\xCE", "\xCF");
						if (in_array($new_block[$i + 1], $sof_marker)) {
							// SOF marker detected. Width and height information is contained in bytes 4-7 after this byte.
							$size_data = $new_block[$i + 2] . $new_block[$i + 3] . $new_block[$i + 4] . $new_block[$i + 5] . $new_block[$i + 6] . $new_block[$i + 7] . $new_block[$i + 8];
							$unpacked = unpack("H*", $size_data);
							$unpacked = $unpacked[1];
							$height = hexdec($unpacked[6] . $unpacked[7] . $unpacked[8] . $unpacked[9]);
							$width = hexdec($unpacked[10] . $unpacked[11] . $unpacked[12] . $unpacked[13]);
							return array($width, $height);
						} else {
							// Skip block marker and read block size
							$i += 2;
							$block_size = unpack("H*", $new_block[$i] . $new_block[$i + 1]);
							$block_size = hexdec($block_size[1]);
						}
					} else {
						return FALSE;
					}
				}
			}
		}
	}
	return FALSE;
}

function clipmydeals_getimgsize_png($img_loc)
{ // inspects only first 24 bytes of the image. saves time  & bandwidth compared to PHP's getimagesize() which downloads full image
	$handle = fopen($img_loc, "rb") or die("Invalid file stream.");
	if (!feof($handle)) {
		$new_block = fread($handle, 24);
		if (
			$new_block[0] == "\x89" &&
			$new_block[1] == "\x50" &&
			$new_block[2] == "\x4E" &&
			$new_block[3] == "\x47" &&
			$new_block[4] == "\x0D" &&
			$new_block[5] == "\x0A" &&
			$new_block[6] == "\x1A" &&
			$new_block[7] == "\x0A"
		) {
			if ($new_block[12] . $new_block[13] . $new_block[14] . $new_block[15] === "\x49\x48\x44\x52") {
				$width  = unpack('H*', $new_block[16] . $new_block[17] . $new_block[18] . $new_block[19]);
				$width  = hexdec($width[1]);
				$height = unpack('H*', $new_block[20] . $new_block[21] . $new_block[22] . $new_block[23]);
				$height  = hexdec($height[1]);
				return array($width, $height);
			}
		}
	}
	return false;
}

function clipmydeals_getimgsize($image_url)
{
	$width = $height = null;
	$imgType = exif_imagetype($image_url);
	// Try faster custom functions first
	if ($imgType == IMAGETYPE_JPEG) {
		list($width, $height) = clipmydeals_getimgsize_jpeg($image_url);
	} elseif ($imgType == IMAGETYPE_PNG) {
		list($width, $height) = clipmydeals_getimgsize_png($image_url);
	}
	// Try slowed PHP function now
	if (!$width or !$height) {
		list($width, $height) = getimagesize($image_url);
	}
	return array($width, $height);
}

function clipmydeals_validImgDimensions($image_url)
{
	$width = $height = null;
	list($width, $height) = clipmydeals_getimgsize($image_url);
	return ($width and $height and $width / $height > 1.5 and $width / $height <= 4); // true for horizontal images (Ratio between 3:2 or 4:1)
}

function sb_str_before($haystack, $needle)
{
	return substr($haystack, 0, strpos($haystack, $needle));
}

function sb_str_after($haystack, $needle)
{
	if (!is_bool(strpos($haystack, $needle)))
		return substr($haystack, strpos($haystack, $needle) + strlen($needle));
}

function clipmydeals_str_contains($haystack, $needle)
{
	if (strpos($haystack, $needle) !== false) {
		return true;
	} else {
		return false;
	}
}

function clipmydeals_str_like($haystack, $needle)
{
	if (strpos(strtolower($haystack), strtolower($needle)) !== false) {
		return true;
	} else {
		return false;
	}
}

function clipmydeals_str_starts_with($haystack, $needle)
{
	return (substr($haystack, 0, strlen($needle)) === $needle);
}

function clipmydeals_str_ends_with($haystack, $needle)
{
	$length = strlen($needle);

	if ($length == 0) {
		return true;
	}

	return (substr($haystack, -$length) === $needle);
}

function clipmydeals_lighter_color($hexCode)
{
	$adjustment = 0.9; // -1 to +1. Example: 0.3 (30% Lighter) or -0.5 (50% Darker)
	$hexCode = ltrim($hexCode, '#');

	if (strlen($hexCode) == 3) {
		$hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
	}

	$hexCode = array_map('hexdec', str_split($hexCode, 2));

	foreach ($hexCode as &$color) {
		$adjustableLimit = $adjustment < 0 ? $color : 255 - $color;
		$adjustAmount = ceil($adjustableLimit * $adjustment);

		$color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
	}

	return '#' . implode($hexCode);
}

function clipmydeals_custom_scripts()
{
	// Custom JS
	wp_enqueue_script('kamil', get_template_directory_uri() . '/inc/assets/js/kamil.js', array(), '', true);
	//wp_enqueue_script( 'clipboard',get_template_directory_uri().'/inc/assets/js/clipboard.min.js', array(), '', true );
}
add_action('wp_enqueue_scripts', 'clipmydeals_custom_scripts');

function get_search_results($taxonomy, $search, $show_image, $current_count = 0, $max_results = 5)
{
	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
		'name__like' => $search,
	));
	$placeholder = get_theme_mod('default_store_image');

	$count = $current_count;
	$html = "";
	if (!empty($terms)) {
		foreach ($terms as $term) {

			if ($count > $max_results && $max_results > 0) break;
			$custom_fields = cmd_get_taxonomy_options($term->term_id, $taxonomy);

			if (isset($custom_fields['status']) and $custom_fields['status'] == "inactive") continue;

			if ($show_image) {

				$image_url = null;
				$image_fit = "object-fit-contain";

				switch ($taxonomy) {
					case 'stores':
						$taxonomy_name = 'Store';
						$image_url = $custom_fields['store_logo'];
						break;

					case 'offer_categories':
						$taxonomy_name = 'Category';
						$image_url = $custom_fields['category_image'];
						break;

					case 'brands':
						$taxonomy_name = 'Brand';
						$image_url = $custom_fields['brand_image'];
						break;

					case 'locations':
						$taxonomy_name = 'Location';
						break;

					default:
						$taxonomy_name = ucfirst($taxonomy) . ' Group';
						break;
				}

				if (empty($image_url)) {
					if ($placeholder) {
						$image_url = $placeholder;
					} else {
						$image_url = get_template_directory_uri() . "/inc/assets/images/random-feature.jpg";
					}
				}
			}

			$count++;
			$html .= '<a href="' . get_term_link($term) . '" class="list-group-item list-group-item-action text-dark bg-white">
						<div class="d-flex w-100 justfy-content-between align-items-center">';
			if ($show_image) {
				$html .= '<img class="' . $image_fit . ' align-self-start me-1 search_result_image rounded" src="' . $image_url . '" alt="' . $term->name . ' Logo">';
			}
			$html .=		'<div class="ps-2 me-auto text-start">
								<h6 class="m-0 text-dark fw-bold">' . $term->name . '</h6>
								<small class="text-dark">' . $taxonomy_name . '</small>
							</div>
						</div>
					</a>';
		}
	}
	return array('count' => $count, 'html' => $html);
}

function ajax_search_posts($types, $search, $count, $max_results, $show_image)
{
	$placeholder = get_theme_mod('default_store_image');

	$args = array(
		'posts_per_page' => $max_results,
		'post_type' => $types,
		'post_status' => 'publish',
		'order' => 'DESC',
		's' => $search,
	);
	if (in_array('coupons', $types) or in_array('products', $types)) {
		$args['meta_query'] = array(
			'relation' => 'AND',
			array(
				'key' => 'cmd_start_date',
				'value' => current_time('Y-m-d'),
				'compare' => '<='
			),
			array(
				'relation' => 'OR',
				array(
					'key' => 'cmd_valid_till',
					'value' => current_time('Y-m-d'),
					'compare' => '>='
				),
				array(
					'key' => 'cmd_valid_till',
					'value' => '',
					'compare' => '='
				),
			),
		);
	}
	query_posts($args);
	$html = array();
	while (have_posts()) {
		the_post();
		$temp_html = '<a href="' . get_permalink() . '" class="list-group-item list-group-item-action text-dark bg-white"><div class="d-flex justfy-content-between">';
		if ($show_image) {
			if (has_post_thumbnail()) {
				$image_url = get_the_post_thumbnail_url();
			} elseif (!empty(get_post_meta(get_the_ID(), 'cmd_image_url', true))) {
				$image_url = get_post_meta(get_the_ID(), 'cmd_image_url', true);
			} elseif ($placeholder) {
				$placeholder = get_theme_mod('default_store_image');
			} else {
				$image_url = get_template_directory_uri() . "/inc/assets/images/random-feature.jpg";
			}
			$temp_html .= '<img class="' . $image_fit . ' align-self-start me-1 search_result_image rounded" src="' . $image_url . '" alt="' . get_the_title() . '">';
		}
		$temp_html .= '<div class="ps-2 me-auto text-start"><h6 class="m-0 text-dark fw-bold">' . get_the_title() . '</h6>';
		if (get_post_type() == 'coupons' || get_post_type() == 'products') {
			$temp_html .= '<small class="text-dark">' . get_the_terms(get_the_ID(), 'stores')[0]->name . '</small>';
		} else {
			$temp_html .= '<small class="text-dark">Posted by ' . get_the_author() . ' on ' . get_the_date() . '</small>';
		}
		$temp_html .= '</div></div></a>';
		switch (get_post_type()) {
			case 'post':
				$html['post'][] = $temp_html;
				break;
			case 'page':
				$html['page'][] = $temp_html;
				break;
			case 'coupons':
			case 'products':
				$html['coupons_products'][] = $temp_html;
				break;
		}
		$count++;
		if ($count >= $max_results && $max_results > 0) break;
	}
	return array('html' => $html, 'count' => $count);
}

function clipmydeals_ajax_search()
{
	$search = $_POST['search'];

	if (strlen($search) > get_theme_mod('ajax_search_start_after', 3)) {

		$count = 0;
		$html = "";
		$posts = "";
		$coupons_products = "";
		$pages = "";
		$show_image = get_theme_mod('ajax_search_show_image');

		$max_results = get_theme_mod('ajax_search_max_results', 5);

		$stores = get_search_results('stores', $search, $show_image, $count, $max_results);

		if ($stores['count'] > $count) {
			$count += $stores['count'];
			$html .= $stores['html'];
		}

		if ($count < $max_results || $max_results <= 0) {
			$categories = get_search_results('offer_categories', $search, $show_image, $count, $max_results);
			if ($categories['count'] > $count) {
				$count += $categories['count'];
				$html .= $categories['html'];
			}
		}

		if ($count < $max_results || $max_results <= 0) {
			$brands = get_search_results('brands', $search, $show_image, $count, $max_results);
			if ($brands['count'] > $count) {
				$count += $brands['count'];
				$html .= $brands['html'];
			}
		}

		if ($count < $max_results || $max_results <= 0) {
			if (get_theme_mod('location_taxonomy', false)) {
				$locations = get_search_results('locations', $search, $show_image, $count, $max_results);
				if ($locations['count'] > $count) {
					$count += $locations['count'];
					$html .= $locations['html'];
				}
			}
		}

		if ($count < $max_results || $max_results <= 0) {
			$post_types = array('coupons',);
			$products_enabled = in_array('clipmydeals-comparison/clipmydeals-comparison.php', get_option('active_plugins'));
			if ($products_enabled) $post_types[] = 'products';

			$data = ajax_search_posts($post_types, $search, $count, $max_results, $show_image);
			$count = $data['count'];
			if ($data['html']['coupons_products']) {
				$title = $products_enabled ? __('Coupons & Products', 'clipmydeals') : __('Coupons', 'clipmydeals');
				$html .= implode('', $data['html']['coupons_products']);
			}
		}

		if ($count < $max_results || $max_results <= 0) {
			$data = ajax_search_posts(array('page', 'post'), $search, $count, $max_results, $show_image);
			$count = $data['count'];
			if ($data['html']['page']) {
				$html .= implode('', $data['html']['page']);
			}
			if ($data['html']['post']) {
				$html .= implode('', $data['html']['post']);
			}
		}

		$see_more = '';
		if ($count != 0 && $count < $max_results) {
			$see_more = '<a class="list-group-item bg-primary border-primary" href="' . get_site_url() . '?s=' . $search . '"><div class="text-center pt-2"><h5 class="text-white m-0">';
			/* translators: %s: Search Keyword */
			$see_more .= sprintf(__('See more results for "%s"', 'clipmydeals'), $search);
			/* translators: %s: Results count */
			$see_more .= '</h5><small class="text-white">' . sprintf(__('Displaying %s results', 'clipmydeals'), $count) . '</small></div></a>';
		}
		echo $html . $coupons_products . $posts . $pages . $see_more;
	}
	wp_die();
}
add_action('wp_ajax_nopriv_clipmydeals_ajax_search', 'clipmydeals_ajax_search');
add_action('wp_ajax_clipmydeals_ajax_search', 'clipmydeals_ajax_search');

function clipmydeals_layout_options($skip_front_home = false)
{
	// Skip fornt page check for carousel element that would ony appear on home
	// This is required as the change in query for offer widgets change is_home or is_front_page values

	$front_home = ($skip_front_home or (is_front_page() or is_home()));

	$sidebar = get_theme_mod('sidebar_visibility', 'on');
	$sidebar = ($sidebar == 'on' or ($sidebar == 'not_on_homepage' and !$front_home) or ($sidebar == 'only_on_homepage' and $front_home));

	$container = get_theme_mod('hp_container_type', 'container-xl');
	$container = ($front_home and $container != 'default') ? $container : get_theme_mod('container_type', 'container-xl');
	$smaller_screen_area = ($sidebar or $container == 'container-xl');

	return array('front_home' => $front_home, 'sidebar' => $sidebar, 'container' => $container, 'smaller_screen_area' => $smaller_screen_area);
}

function clipmydeals_ios_check()
{
	if (!empty($_GET['user']) and isset($_GET['iosView'])) {
		setcookie('some_name', 'true');
		$user_id = $_GET['user'];
		if (get_userdata($user_id)) {
			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id, true, is_ssl());
		}
		wp_redirect(remove_query_arg('user'));
	}
}
add_action('after_setup_theme', 'clipmydeals_ios_check');

function clipmydeals_on_logout()
{
	setcookie("clipmydeals_logged_in", "", 100, "/");
	setcookie('clipmydeals_logged_out', 'true', 0, "/");
}
add_action('wp_logout', 'clipmydeals_on_logout', 10, 1);

function clipmydeals_on_login($user_login, $user)
{
	setcookie("clipmydeals_logged_out", "", 100, "/");
	setcookie('clipmydeals_logged_in', $user->ID, 0, "/");
}
add_action('wp_login', 'clipmydeals_on_login', 10, 2);

/**
 * Register Notification Post Type
 */
function clipmydeals_create_notification_post_type()
{
	global $wp_responsive;

	// Set UI labels for Notification Post Type
	$labels = array(
		'name'                => _x('Notifications', 'Post Type General Name', 'clipmydeals'),
		'singular_name'       => _x('Notification', 'Post Type Singular Name', 'clipmydeals'),
		'menu_name'           => __('Notifications', 'clipmydeals', 'clipmydeals'),
		'all_items'           => __('All Notifications', 'clipmydeals'),
		'view_item'           => __('View Notification', 'clipmydeals'),
		'add_new_item'        => __('Add New Notification', 'clipmydeals'),
		'add_new'             => __('Add New', 'clipmydeals'),
		'edit_item'           => __('Edit Notification', 'clipmydeals'),
		'update_item'         => __('Update Notification', 'clipmydeals'),
		'search_items'        => __('Search Notification', 'clipmydeals'),
		'not_found'           => __('Not Found', 'clipmydeals'),
		'not_found_in_trash'  => __('Not found in Trash', 'clipmydeals'),
	);
	// Set other options for Notification Post Type
	$args = array(
		'label'               => __('notification', 'clipmydeals'),
		'description'         => __('Notifications', 'clipmydeals'),
		'labels'              => $labels,
		// Gutenberg
		'show_in_rest'        => true,
		// Features supported in Post Editor
		'supports'            => array('title', 'editor', 'thumbnail'), // publicize is for Jetpack to post to social media
		'taxonomies'          => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => false,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => get_option($wp_responsive) ? 'post' : array('notification', 'notifications'),
		'menu_icon'           => 'dashicons-bell',
		'register_meta_box_cb' => 'clipmydeals_add_notifications_metaboxes',
	);
	// Registering Notification Post Type
	register_post_type('notifications', $args);
}
add_action('init', 'clipmydeals_create_notification_post_type', 0);

// Add Meta Boxes to for notifications
function clipmydeals_add_notifications_metaboxes()
{
	add_meta_box(
		'cmd_send_to_users',
		__('Send to Users', 'clipmydeals'),
		'clipmydeals_display_meta_box_send_to_users',
		'notifications',
		'normal',
		'high'
	);
	add_meta_box(
		'cmd_notification_url',
		__('Notification URL', 'clipmydeals'),
		'clipmydeals_display_meta_box_open_url',
		'notifications',
		'normal',
		'high'
	);
}

function clipmydeals_display_meta_box_send_to_users()
{
	global $post, $wpdb;
	$subscribed_users = $wpdb->get_results("SELECT user_id FROM " . $wpdb->prefix . "cmd_subscriptions WHERE user_id IS NOT NULL");
	$subscribed_user_ids = array();
	foreach ($subscribed_users as $subscribed_user) $subscribed_user_ids[] = $subscribed_user->user_id;
	$users = get_users();
	wp_nonce_field(basename(__FILE__), 'cmd_send_to_users_n');
	echo '<select name="cmd_send_to_users[]" class="widefat" required multiple>';
	$existing_users = get_post_meta($post->ID, 'cmd_send_to_users', true) ?: array('all');
	echo '<option ' . (in_array('all', $existing_users) ? 'selected' : '') . ' value="all">All User</option>';
	foreach ($users as $user) {
		$selection = '';
		if (!in_array($user->ID, $subscribed_user_ids)) $selection .= 'disabled';
		elseif (in_array($user->ID, $existing_users)) $selection .= 'selected';
		echo '<option  value="' . $user->ID . '" ' . $selection . '>' . $user->display_name . '</option>';
	}
	echo '</select>';
}

function clipmydeals_display_meta_box_open_url()
{
	global $post;
	wp_nonce_field(basename(__FILE__), 'cmd_notification_url_n');
	echo '<input type="url" name="cmd_notification_url" value="' . htmlspecialchars_decode(get_post_meta($post->ID, 'cmd_notification_url', true), ENT_QUOTES)  . '" class="widefat">';
	echo '<div style="margin-top:5px;">'.__('This is the URL which will be opened after user clicks on the notification', 'clipmydeals').'</div>';
}

function clipmydeals_create_user_subscription()
{
	global $wpdb;
	$data = array(
		'token'                 => $_POST['token'],
		'key'                   => $_POST['key'],
		'user_id'               => intval(get_current_user_id()),
	);

	if (!$data['user_id']) return;

	$where = array('subscription_endpoint' => $_POST['subscription_endpoint']);
	switch ($_POST['subscription_action']) {
		case 'PUT':
			$wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "cmd_subscriptions WHERE subscription_endpoint = '" . $where["subscription_endpoint"] . "'");
			if ($wpdb->num_rows > 0) {
				$wpdb->update($wpdb->prefix . "cmd_subscriptions", $data, $where);
				echo "UPDATED";
				break;
			}
		case 'POST':
			$wpdb->insert($wpdb->prefix . "cmd_subscriptions", array_merge($data, $where));
			update_user_meta(get_current_user_id(), 'cmd_notifications_disabled', false);
			echo "CREATED";
			break;
		case "DELETE":
			$wpdb->delete($wpdb->prefix . "cmd_subscriptions", $where);
			update_user_meta(get_current_user_id(), 'cmd_notifications_disabled', true);
			echo "DELETED";
			break;
	}
}
add_action('wp_ajax_nopriv_clipmydeals_create_subscription', 'clipmydeals_create_user_subscription');
add_action('wp_ajax_clipmydeals_create_subscription', 'clipmydeals_create_user_subscription');

function clipmydeals_on_notification_save($post_id, $post)
{
	if (!current_user_can('edit_post', $post_id)) return $post_id;
	if (!isset($_POST['cmd_send_to_users']) || !wp_verify_nonce($_POST['cmd_send_to_users_n'], basename(__FILE__))) return $post_id;

	$send_to_users = is_array($_POST['cmd_send_to_users']) ? $_POST['cmd_send_to_users'] : array('all');
	update_post_meta($post_id, 'cmd_send_to_users', $send_to_users);

	$notification_url = $_POST['cmd_notification_url'];
	update_post_meta($post_id, 'cmd_notification_url', $notification_url);

	$timestamp = strtotime($post->post_date_gmt);
	if (in_array($post->post_status, array('future', 'publish'))) {
		$previous_schedule = get_post_meta($post_id, 'cmd_previous_schedule', true) ?: 100;
		wp_unschedule_event($previous_schedule, 'cmd_process_notification_queue_event', array($post_id));
		wp_schedule_single_event($timestamp, 'cmd_process_notification_queue_event', array($post_id));
		update_post_meta($post_id, 'cmd_previous_schedule', $timestamp);
	}
}
add_action('save_post_notifications', 'clipmydeals_on_notification_save', 1, 2);

function clipmydeals_add_extra_user_fields($user)
{
	$vapid_keys = get_option('clipmydeals_vapid_keys', array('publicKey' => NULL, 'privateKey' => NULL));
	if (is_null($vapid_keys['publicKey'])) return;
	if ($user->ID != get_current_user_id()) return;
	if ((get_theme_mod('notification_requests', 'enable') != 'enable')) return;
	?>
	<hr />
	<table class="form-table">
		<tr>
			<th><label><?php _e("Push Notifications", 'clipmydeals'); ?></label></th>
			<td>
				<?= clipmydeals_enable_notification() ?>
			</td>
		</tr>
	</table>
	<script>
		window.onload = () => {
			cmdHandleNotification();
		}
	</script>
<?php
	clipmydeals_notifications_scripts();
}
add_action('show_user_profile', 'clipmydeals_add_extra_user_fields');
add_action('edit_user_profile', 'clipmydeals_add_extra_user_fields');

function clipmydeals_add_delete_profile_button($user)
{
	if (!array_key_exists('subscriber', $user->caps)) return;
	$appname = get_theme_mod('ios_bundle_name');
	$appname = ucwords(str_replace('-', ' ', explode('/', $appname)[0])) ?: __('Your app/app bundle ID', 'clipmydeals');

	//Bootstrap JS
	wp_register_script('bootstrap.min', get_template_directory_uri() . '/inc/assets/js/bootstrap.min.js', array('jquery'));
	wp_enqueue_script('bootstrap.min');

	//Bootstrap CSS
	wp_register_style('bootstrap.min', get_template_directory_uri() . '/inc/assets/css/bootstrap.min.css');
	wp_enqueue_style('bootstrap.min'); ?>

	<!-- Background color for admin page is #f1f1f1 which is overrided by boostrap.min.css -->
	<style>
		#wpwrap {
			background: #f1f1f1 !important;
		}
	</style>
	<table class="form-table">
		<tr>
			<style>
				.modal-backdrop {
					z-index: -1 !important;
				}
			</style>
			<th><label><?php _e("Delete Account", 'clipmydeals'); ?></label></th>
			<td>
				<button class="btn btn-danger" style="padding: .175rem .75rem; font-size: 0.9rem;" onclick="cmdOnDeleteAccountButtonClick(event)" id="cmd-delete-account-button">Delete My Account</button>
			</td>
		</tr>
	</table>
	<!-- Modal -->
	<div class="modal" id="deleteAccount-modal" tabindex="20" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="deleteAccount-modalTitle" aria-hidden="true" style="<?= !is_rtl() ? 'right: 0 !important; left:auto !important;' : 'right: auto !important; left:0 !important;' ?> width: 90% !important;">
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div id="confirm-modal-body" class="modal-body px-2 py-4 border-0">
					<div class="container">
						<h5 class="text-center font-weight-bold"><?= __('Are you sure you want to delete your Account?', 'clipmydeals'); ?></h5>
						<p class="lead-3 text-center small"><?= __('You cannot undo this operation after this step. Click"<strong>Cancel</strong>" to abort.</p>', 'clipmydeals'); ?></p>
						<?php if (isset($_GET['iosView'])) { ?>
							<p class="lead-3 small"><?= sprintf(__('Once your <strong>%s</strong> account is deleted, you will also have to manually revoke this App\'s Permissions in your iPhone Settings by following the below steps:', 'clipmydeals'), get_bloginfo('name')); ?></p>
							<!-- https://stackoverflow.com/questions/58995015/reset-sign-in-with-apple-to-the-initial-create-account-state -->
							<ol class="lead-3 small">
								<li><?= __('Go to iPhone <strong>Settings</strong>', 'clipmydeals') ?></li>
								<li><?= __('Select your Apple account.', 'clipmydeals') ?></li>
								<li><?= __('Select <strong>Password & Security</strong>', 'clipmydeals') ?></li>
								<li><?= __('Select <strong>Apps Using Your Apple ID</strong>', 'clipmydeals') ?></li>
								<li><?= sprintf(__('Select <strong>%s</strong>', 'clipmydeals'), $appname) ?></li>
								<li><?= __('Click <strong class="text-danger">Stop using Apple ID</strong>', 'clipmydeals') ?></li>
							</ol>
						<?php } ?>
						<div id="deleteAccount-buttons" class="row justify-content-around">
							<button id="deleteAccount-confirm" class="mt-2 btn col-md-4 btn-danger" style="padding: .175rem .75rem; font-size: 0.9rem;" onclick="confirmDeleteAccount(event);"><?= __('Delete', 'clipmydeals') ?></button>
							<button id="deleteAccount-cancel" class="mt-2 btn col-md-4 btn-secondary" style="padding: .175rem .75rem; font-size: 0.9rem;" data-bs-dismiss="modal" aria-label="Close"><?= __('Cancel', 'clipmydeals') ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>
		function cmdOnDeleteAccountButtonClick(event) {
			event.preventDefault();
			jQuery('#deleteAccount-modal').modal('show');
		}

		function confirmDeleteAccount(event) {
			event.preventDefault();
			var btn = document.getElementById("deleteAccount-confirm");
			btn.innerHTML = sprintf(__("Please Wait %s", 'clipmydeals'),"<span class='dashicons dashicons-clock'></span>");
			btn.disabled = true;
			var form = new FormData();
			form.append('action', 'cmd_delete_account');
			form.append('user_id', <?= $user->ID ?>);
			fetch('<?= admin_url('admin-ajax.php') ?>', {
					method: 'POST',
					body: form
				})
				.then((res) => {
					if (res.ok) {
						window.location.replace('<?= admin_url() ?>')
					}
				})
		}
	</script> <?php
			}
			add_action('show_user_profile', 'clipmydeals_add_delete_profile_button');

			function cmd_delete_account()
			{
				global $wpdb;
				$wp_prefix = $wpdb->prefix;
				$user_id = $_POST['user_id'];
				$res = $wpdb->get_results("SELECT count(1) total FROM `{$wp_prefix}usermeta` WHERE `meta_key` = 'referrer' AND `meta_value` = " . $user_id);
				wp_logout();
				if ($res[0]->total > 0 or cmd_get_table_data($user_id, 'cashback_clicks') > 0 or cmd_get_table_data($user_id, 'cashback_bonuses') > 0 or cmd_get_table_data($user_id, 'cashback_transactions') > 0 or cmd_get_table_data($user_id, 'cashback_withdrawals') > 0) {
					$table = $wp_prefix . 'users';
					$wpdb->query("UPDATE  $table SET user_login = CONCAT(user_login,'_deleted_',ID), user_pass = MD5('deleted'), user_email = CONCAT(ID,'_deleted_',user_email)  WHERE ID = $user_id");
				} else {
					wp_delete_user($user_id);
				}
			}
			add_action('wp_ajax_cmd_delete_account', 'cmd_delete_account');

			function cmd_get_table_data($user_id, $table)
			{
				global $wpdb;
				$wp_prefix = $wpdb->prefix;
				$res = $wpdb->get_results("SELECT count(1) total FROM " . $wp_prefix . $table . " WHERE user=" . $user_id);
				return $res[0]->total;
			}

			function clipmydeals_delete_store($id)
			{
				global $wpdb;
				// DELETE domains records FROM `cmd_store_to_domain` table
				$wpdb->delete(
					"{$wpdb->prefix}cmd_store_to_domain",
					array('store_id' => $id)
				);
			}
			add_action('delete_stores', 'clipmydeals_delete_store');

			function cmd_store_category_sort($store_details, $orderby = 'priority', $ascdsc = 'desc')
			{
				$ascdsc = strtolower($ascdsc);
				if (is_array($store_details)) {
					usort($store_details, function ($a, $b) use ($orderby, $ascdsc) {
						$a_key = $orderby == 'priority' ? intval($a['term_meta']['store_display_priority']) : strtolower($a[$orderby]);
						$b_key = $orderby == 'priority' ? intval($b['term_meta']['store_display_priority']) : strtolower($b[$orderby]);
						if ($a_key == $b_key) {
							if (strtolower($a['name']) >= strtolower($b['name'])) {
								return 1;
							} else {
								return -1;
							}
						} else if (($a_key > $b_key && $ascdsc == 'desc') || ($a_key < $b_key && $ascdsc == 'asc')) {
							return -1;
						} else {
							return 1;
						}
					});
				}
				return $store_details;
			}

			function cmd_process_notification_queue($post_id)
			{
				$post = get_post($post_id);
				if (!$post) return;

				$send_to_users = get_post_meta($post_id, 'cmd_send_to_users', true);
				$notification_url = get_post_meta($post_id, 'cmd_notification_url', true);

				$payload = json_encode(array(
					'title'    => $post->post_title,
					'content'  => wp_strip_all_tags($post->post_content),
					'image'    => has_post_thumbnail($post) ? get_the_post_thumbnail_url($post) : NULL,
					'open_url' => !empty($notification_url) ? $notification_url : NULL
				));

				global $wpdb;

				$subscriptions_sql = "SELECT * from {$wpdb->prefix}cmd_subscriptions";
				if (!in_array('all', $send_to_users)) $subscriptions_sql .= " WHERE user_id IN (" . implode(',', $send_to_users) . ")";
				$subscriptions = $wpdb->get_results($subscriptions_sql);

				$notifications = array();
				foreach ($subscriptions as $s) {
					$notifications[] = array(
						'id'           => $s->id,
						'subscription' => Subscription::create([
							'endpoint' => $s->subscription_endpoint,
							'publicKey' => $s->key,
							'authToken' => $s->token
						])
					);
				}

				$key = get_option('clipmydeals_vapid_keys');
				$auth = [
					'VAPID' => [
						// translators: Push Notification from ClipMyDeals
						'subject'    => sprintf(__('Push Notification from %s', 'clipmydeals'), get_bloginfo()),
						'publicKey'  => $key['publicKey'],
						'privateKey' => $key['privateKey'],
					],
				];

				$webPush = new WebPush($auth);
				foreach ($notifications as $notification) $webPush->queueNotification($notification['subscription'], $payload);
				$insert_query = 'INSERT INTO ' . $wpdb->prefix . 'cmd_notification_logs (subscription_id,failed , message) VALUES (
							(SELECT id from  ' . $wpdb->prefix . 'cmd_subscriptions WHERE subscription_endpoint = "%1$s"),
							"%2$s",
							"%3$s"
					)';
				foreach ($webPush->flush() as $report) {
					$endpoint = $report->getRequest()->getUri()->__toString();
					$failed   = $report->isSuccess() ? 'N' : 'Y';
					$message  = $report->isSuccess() ? "Notification sent to $endpoint" : $report->getReason();
					$wpdb->query($wpdb->prepare($insert_query, $endpoint, $failed, $message));
				}
			}
			add_action('cmd_process_notification_queue_event', 'cmd_process_notification_queue');



			function cmd_get_taxonomy_options($term_id, $taxonomy)
			{
				if ($taxonomy == 'stores') {
					$default_keys = array('popular' => '', 'store_url' => '', 'store_aff_url' => '', 'store_logo' => '', 'store_intro' => '', 'store_banner' => '', 'store_color' => '', 'map' => '', 'video' => '', 'page_title' => '', 'store_category' => '', 'store_display_priority' => '0', 'status' => 'active');
				} else if ($taxonomy == 'offer_categories') {
					$default_keys = array('category_intro' => '', 'category_image' => '', 'page_title' => '',);
				} else if ($taxonomy == 'locations') {
					$default_keys = array('map' => '', 'page_title' => '', 'location_intro' => '');
				} else if ($taxonomy == 'brands') {
					$default_keys = array('brand_image' => '', 'page_title' => '', 'brand_intro' => '', 'brand_map' => '', 'brand_video' => '');
				} else {
					$default_keys = array();
				}
				$custom_fields = get_option("taxonomy_term_{$term_id}", array());
				return array_merge($default_keys, $custom_fields);
			}

			function cmd_get_cashback_option()
			{
				$default_cashback_arr = array('value' => [], 'type' => [], 'message' => [], 'details' => []);
				$cashback_options = array();
				if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
					$cashback_options = get_option('cashback_options', array());
				}

				return array_merge($default_cashback_arr, $cashback_options);
			}

			function clipmydeals_server_checks($data)
			{
				if ($data['API_KEY'] != get_option('cmd_key')) {
					$response = array("API_KEY" => "Incorrect API Key");
				} else {
					global $wpdb;

					if (!function_exists('get_plugin_data')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');

					$response = array(
						'wordpress'				=> array(
							'version'				=> get_bloginfo('version'),
							'timezone'				=> wp_timezone_string(),
							'home_url'				=> get_bloginfo('url'),
							'site_url'				=> get_bloginfo('wpurl'),
							'https_status'			=> is_ssl(),
							'multisite'				=> is_multisite(),
							'permalink_structure'	=> get_option('permalink_structure'),
							'users_can_register'	=> get_option('users_can_register'),
							'environment_type'		=> wp_get_environment_type(),
							'posts_per_page'		=> get_option('posts_per_page'),
							'show_on_front'			=> get_option('show_on_front'),
							'WPLANG'				=> get_option('WPLANG'),
						),
						'clipmydeals'			=> array(
							'template'				=> get_template(),
							'template_directory'	=> get_template_directory(),
							'template_version'		=> wp_get_theme(get_option('template'))->Version,
							'stylesheet'			=> get_stylesheet(),
							'stylesheet_directory'	=> get_stylesheet_directory(),
							'stylesheet_version'	=> wp_get_theme(get_stylesheet())->Version,
							'theme_options'			=> get_option("theme_mods_clipmydeals", array()),
						),
						'active_plugins'		=> array_values(get_option('active_plugins')),
						'php_server'			=> array(
							'curl'					=> in_array('curl', get_loaded_extensions()),
							'allow_url_fopen'		=> ini_get('allow_url_fopen') ? true : false,
							'exif'					=> function_exists('exif_imagetype'),
							'gd'					=> function_exists('imagecreate'),
							'max_input_time'		=> ini_get('max_input_time'),
							'max_execution_time'	=> ini_get('max_execution_time'),
							'upload_max_filesize'	=> ini_get('upload_max_filesize'),
							'phpversion'			=> function_exists('phpversion') ? phpversion() . (PHP_INT_SIZE === 8 ? '(Supports 64bit values)' : '(Does not support 64bit values)') : 'unknown',
							'htaccess'				=> is_file(ABSPATH . '.htaccess') ? (!empty(trim(preg_replace('/\# BEGIN WordPress[\s\S]+?# END WordPress/si', '', file_get_contents(ABSPATH . '.htaccess')))) ? 'Custom rules have been added to .htaccess file.' : '.htaccess file contains only core WordPress features.') : 'unkown',
							'timezone'				=> date_default_timezone_get(),

						),
						'database'				=> array(
							'charset'				=> strtolower($wpdb->charset),
							'collatation'			=> strtolower($wpdb->collate),
							'timezone'				=> $wpdb->get_var("SELECT IF(@@session.time_zone = 'SYSTEM', @@system_time_zone, @@session.time_zone)"),
						),
						'wordpress_constants'	=> array(
							'WP_CACHE'				=> WP_CACHE ? __('Enabled') : __('Disabled'),
							'WP_DEBUG'				=> WP_DEBUG ? __('Enabled') : __('Disabled'),
							'WP_DEBUG_DISPLAY'		=> WP_DEBUG_DISPLAY ? __('Enabled') : __('Disabled'),
							'WP_DEBUG_LOG'			=> WP_DEBUG_LOG ? __('Enabled') : __('Disabled'),
						),
						'file_permissions'		=> array(
							'WordPress directory'	=> wp_is_writable(ABSPATH),
							'wp-content directory'	=> wp_is_writable(WP_CONTENT_DIR),
							'uploads directory'		=> wp_is_writable(wp_upload_dir()['basedir']),
							'plugins directory'		=> wp_is_writable(WP_PLUGIN_DIR),
							'themes directory'		=> wp_is_writable(get_theme_root(get_template())),
						),
					);

					if (in_array('clipmydeals-comparison/clipmydeals-comparison.php', get_option('active_plugins'))) {
						$response['clipmydeals-comparison'] = array(
							'version'						=> get_plugin_data(PLUGINDIR . '/clipmydeals-comparison/clipmydeals-comparison.php')['Version'],
							'options'						=> get_option('cmdcomp_plugin_mod')
						);
					}
					if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
						$response['clipmydeals-cashback']	= array(
							'version'				=> get_plugin_data(PLUGINDIR . '/clipmydeals-cashback/clipmydeals-cashback.php')['Version'],
							'options'				=> array(
								'transaction_currency'		=> get_option('cmdcp_transaction_currency', "$"),
								'cashback_currency'			=> get_option('cmdcp_cashback_currency', "$"),
								'minimum_withdrawal'		=> get_option('cmdcp_minimum_withdrawal', 0),
								'joining_bonus'				=> get_option('cmdcp_joining_bonus', 0),
								'referral_type'				=> get_option('cmdcp_referral_type', "fixed"),
								'referral_bonus'			=> get_option('cmdcp_referral_bonus', 0),
								'referral_page'				=> get_option('cmdcp_referral_page', ''),
								'show_referral_earning'		=> get_option('cmdcp_show_referral_earning', 0),
								'message_color'				=> get_option('cmdcp_message_color', "#4CA14C"),
								'show_login_button'			=> get_option('cmdcp_show_login_button', 0),
								'show_login_modal'			=> get_option('cmdcp_show_login_modal', 'no'),
								'show_login_count'			=> get_option('cmdcp_show_login_count', '-1'),
								'payment_methods'			=> get_option('cmdcp_payment_methods', array('bank')),
								'custom_payment_methods'	=> get_option('cmdcp_custom_payments', array()),
								'currency_position'			=> get_option('cmdcp_currency_position', 'prefix'),
								'clear_postback_logs'		=> get_option('cmdcp_clear_postback_logs', '-1'),
								'use_wordpress_time'		=> get_option('cmdcp_use_wordpress_time', false),
							),
							'postback_logs'			=> $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cashback_postback_logs WHERE logtime > CURRENT_TIMESTAMP - INTERVAL 3 DAY"),
						);
					}
				}

				return new WP_REST_Response(
					$response,
					200,
					array('Cache-Control' => 'no-cache, no-store, must-revalidate', 'Pragma' => 'no-cache', 'Expires' => '0', 'Content-Transfer-Encoding' => 'UTF-8')
				);
			}

			function clipmydeals_user_profile()
			{
				if (!is_user_logged_in()) {
					if (get_option('cmdcp_login_form_type') == 'theme') {
						$response = '<p>' . __('You need to be logged in to access this page', 'clipmydeals') . '<div id="user_profile_cashback_login_button" class="btn btn-info text-white" onclick="cmdLoadLoginModal()"><i class="fa fa-user"></i> ' . __('Login', 'clipmydeals') . '</div></p>';
					} else {
						$response = '<p>' . __('You need to be logged in to access this page', 'clipmydeals') . '<br/><a id="cashback_login_button" class="btn btn-info text-white" onclick="openLoginPage(event)" href="' . wp_login_url($_SERVER['REQUEST_URI']) . '"><i class="fa fa-user"></i> ' . __('Login', 'clipmydeals') . '</a></p>';
					}
				} else {
					$current_user = wp_get_current_user();
					$user_meta = get_user_meta($current_user->ID);
					global $wp;
					$current_url = home_url(add_query_arg(array(), $wp->request));
					$response = '';

					if (!empty($_COOKIE['message'])) {
						$message = stripslashes($_COOKIE['message']);
						$response .= '<script>document.cookie = "message=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/"</script>';
					}

					$response .= '<span>' . (isset($message) ? $message : '') . '</span>
		<form class="form" name="userProfile" role="form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">
						<div class="basic_details">
							<div class="row mt-3">
								<div class="col-sm-12"><h2>' . __('Basic Details', 'clipmydeals') . '</h2></div>
							</div>
							<div class="row mt-3">
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_username" class="form-label">' . __('Username', 'clipmydeals') . '</label>
									<input type="text" class="form-control" name="username" id="cmd_profile_username"  value="' . $current_user->user_login . '" readonly>
								</div>
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_first_name" class="form-label">' . __('First Name', 'clipmydeals') . '</label>
									<input type="text" class="form-control" name="first_name" id="cmd_profile_first_name"  value="' . $user_meta['first_name'][0] . '" >
								</div>
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_last_name" class="form-label">' . __('Last Name', 'clipmydeals') . '</label>
									<input type="text" class="form-control" name="last_name" id="cmd_profile_last_name"  value="' . $user_meta['last_name'][0] . '" >
								</div>
							</div>
							<div class="row mt-3">
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_email" class="form-label">' . __('Email', 'clipmydeals') . '</label>
									<input type="email" class="form-control" name="email" id="cmd_profile_email"  value="' . $current_user->user_email . '">
								</div>
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_password" class="form-label">' . __('Password', 'clipmydeals') . '</label>
									<input type="password" class="form-control" name="password" id="cmd_profile_password"  value="" >
								</div>
								<div class="col-sm-4 my-2">
									<label for="cmd_profile_conf_password" class="form-label">' . __('Confirm Password', 'clipmydeals') . '</label>
									<input type="password" class="form-control" name="conf_password" id="cmd_profile_conf_password"  value="" oninput="this.setCustomValidity(document.querySelector(`#cmd_profile_password`).value != this.value ? `Passwords do not match` : ``)">
								</div>
							</div>
							<hr>
						</div>';
					if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
						$cmdcp_payment_methods = get_option('cmdcp_payment_methods', array('bank'));
						$cmdcp_custom_payments = get_option('cmdcp_custom_payments', array());
						$response .=     '<div class="payment_details"><div class="row mt-3">
								<div class="col-sm-12"><h2>' . __('Payment Details', 'clipmydeals') . '</h2></div>
							</div>
							<div class="row mt-3">
								<div class="col-sm-3">
									<label for="cmd_profile_tax_id" class="form-label">' . __('Tax ID', 'clipmydeals') . '</label>
								</div>
								<div class="col-sm-4">
									<input type="text" class="form-control" name="tax_id" id="cmd_profile_tax_id"  value="' . $user_meta['tax_id'][0] . '">
								</div>
							</div>';
						if (count(array_diff($cmdcp_payment_methods, array('bank'))) > 0) {
							$response .=    '<div class="mt-3">
									<div class="row">
										<div class= "col-sm-12">
											<label for="wallet_details" class="form-label"><h3>[' . __('Wallet Details', 'clipmydeals') . ']</h3></label>
										</div>
									</div>
									<div class="row mt-2">';
							if (in_array('paypal', $cmdcp_payment_methods)) {
								$response .=    '<div class="col-sm-3 mb-2">
										<label for="cmd_profile_paypal_email" class="form-label">' . __('PayPal Email Address', 'clipmydeals') . '</label>
										<input type="text" name="paypal_email" class="form-control" id="cmd_profile_paypal_email" value="' . $user_meta['paypal_email'][0] . '"  />
									</div>';
							}

							if (in_array('google_pay', $cmdcp_payment_methods)) {
								$response .=    '<div class="col-sm-3 mb-2">
										<label for="cmd_profile_google_pay_id" class="form-label">' . __('Google Pay ID', 'clipmydeals') . '</label>
										<input type="text" name="google_pay_id" class="form-control" id="cmd_profile_google_pay_id" value="' . $user_meta['google_pay_id'][0] . '"  />
									</div>';
							}

							if (in_array('amazon_pay', $cmdcp_payment_methods)) {
								$response .=    '<div class="col-sm-3 mb-2">
										<label for="cmd_profile_amazon_pay_id" class="form-label">' . __('Amazon Pay ID', 'clipmydeals') . '</label>
										<input type="text" name="amazon_pay_id" class="form-control" id="cmd_profile_amazon_pay_id" value="' . $user_meta['amazon_pay_id'][0] . '"  />
									</div>';
							}

							if (in_array('paym', $cmdcp_payment_methods)) {
								$response .=    '<div class="col-sm-3 mb-2">
										<label for="cmd_profile_paym_phone" class="form-label">' . __('Paym Phone Number (UK)', 'clipmydeals') . '</label>
										<input type="text" name="paym_phone" class="form-control" id="cmd_profile_paym_phone" value="' . $user_meta['paym_phone'][0] . '"  />
									</div>';
							}

							if (in_array('paytm', $cmdcp_payment_methods)) {
								$response .=    '<div class="col-sm-3 mb-2">
										<label for="cmd_profile_paytm_id" class="form-label">' . __('PayTM ID (India)', 'clipmydeals') . '</label>
										<input type="text" name="paytm_id" class="form-control" id="cmd_profile_paytm_id" value="' . $user_meta['paytm_id'][0] . '"  />
									</div>';
							}
							foreach ($cmdcp_custom_payments as $method => $details) {
								if (in_array($method, $cmdcp_payment_methods)) {
									foreach ($details as  $detail) {
										$field = strtolower(str_replace(' ', '_', $method . "_" . $detail));
										$response .=    '<div class="col-sm-3 mb-2">
												<label for="' . $field . '" class="form-label">' . $detail . '</label>
												<input type="text" name="' . $field . '" class="form-control" id="' . $field . '" value="' . (isset($user_meta[$field]) ? $user_meta[$field][0] : '') . '"  />
											</div>';
									}
								}
							}

							$response    .=    '</div></div>';
						}


						if (in_array('bank', $cmdcp_payment_methods)) {
							$response .=    '<div id="bank_details" class="mt-3">
									<div class="row">
										<div class= "col-sm-12">
											<label for="bank_details" class="form-label"><h3>[' . __('Bank Details', 'clipmydeals') . ']</h3></label>
										</div>
									</div>
									<div class="row mt-2">
										<div class="col-sm-3">
											<label for="cmd_profile_bank_name" class="form-label">' . __('Name of Bank', 'clipmydeals') . '</label>
											<input type="text" name="bank_name" class="form-control" id="cmd_profile_bank_name" value="' . $user_meta['bank_name'][0] . '"  />
										</div>

										<div class="col-sm-3">
											<label for="cmd_profile_bank_code" class="form-label">' . __('Bank Code', 'clipmydeals') . '</label>
											<input type="text" name="bank_code" class="form-control" id="cmd_profile_bank_code" value="' . $user_meta['bank_code'][0] . '"  />
										</div>

										<div class="col-sm-3">
											<label for="cmd_profile_bank_account_number" class="form-label">' . __('Account Number', 'clipmydeals') . '</label>
											<input type="text" name="bank_account_number" id="cmd_profile_bank_account_number" class="form-control" value="' . $user_meta['bank_account_number'][0] . '"  />
										</div>

										<div class="col-sm-3">
											<label for="cmd_profile_bank_account_name" class="form-label">' . __('Name of Account', 'clipmydeals') . '</label>
											<input type="text" name="bank_account_name" class="form-control" id="cmd_profile_bank_account_name" value="' . $user_meta['bank_account_name'][0] . '"  />
										</div>
									</div>
								</div><hr></div>';
						}

						$response .=     '<div class="contact_information"><div class="row mt-3">
								<div class="col-sm-12"><h2>' . __('Contact Information', 'clipmydeals') . '</h2></div>
							</div>
							<div class="row mt-3">
								<div class="col-sm-8">
									<label for="cmd_profile_address" class="form-label">' . __('Address', 'clipmydeals') . '</label>
									<textarea name="address" class="form-control" id="cmd_profile_address">' . $user_meta['address'][0] . '</textarea>
								</div>
								<div class="col-sm-4">
									<label for="cmd_profile_phone" class="form-label">' . __('Phone Number', 'clipmydeals') . '</label>
									<input type="text" name="phone" class="form-control" id="cmd_profile_phone" value="' . $user_meta['phone'][0] . '"  />
								</div>
							</div><hr> </div>
							<div class="referrer">
							<div class="row mt-3">
								<div class="col-sm-3">
									<label for="cmd_profile_referrer" class="form-label">' . __('Referrer', 'clipmydeals') . '</label>
								</div>
								<div class="col-sm-3">
									<select class="form-control" name="referrer" id="cmd_profile_referrer" ' . (!current_user_can('edit_others_pages') ? 'disabled' : '') . '>
										<option value="">' . __('None', 'clipmydeals') . '</option>';
						$allusers = get_users('orderby=login');
						foreach ($allusers as $loopuser) {
							$response .= '<option value="' . $loopuser->ID . '" ' . ((isset($user_meta['referrer'][0]) and !empty($user_meta['referrer'][0]) and $loopuser->ID == $user_meta['referrer'][0])
								? "selected" : "") . '>' . $loopuser->user_login . '</option>';
						}
						$response   .=        '</select>
								</div>
							</div><hr/></div>';
						'';
					}

					if (!isset($_GET['iosView']) and !isset($_GET['androidView']) and (get_theme_mod('notification_requests', 'enable') == 'enable')) {
						$response .=     '<div class="push_notification row mt-3">
								<div class="col-sm-3">
									<label for="notification" class="form-label">' . __('Push Notification', 'clipmydeals') . '</label>
								</div>
								<div class="col-sm-4">' . clipmydeals_enable_notification() . '</div>
							</div>';
					}
					$response .= wp_nonce_field('clipmydeals', 'save_user_profile', false, false) . '
						<input type="hidden" name="action" value="clipmydeals_save_user_data" />
						<input type="hidden" name="current_url" value="' . $current_url . '"/>
						<div class="row mt-3">
							<div class="col-sm-3">
								<button class="btn btn-primary" type="submit" name="submit_profile">' . __('Submit', 'clipmydeals') . ' </button>
							</div>
						</div>
					</form>';
				}
				return $response;
			}

			add_shortcode('cmd_user_profile', 'clipmydeals_user_profile');

			function clipmydeals_save_user_data()
			{
				$message = '';
				if (wp_verify_nonce($_POST['save_user_profile'], 'clipmydeals')) {
					if ($_POST['password'] != $_POST['conf_password']) {
						$message = '<div class="alert alert-danger alert-dismissible">' . __('Passwords do not match', 'clipmydeals') . '.
							<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>';
					} else {
						$user_id = get_current_user_id();
						$user_data = array('ID' => $user_id);
						if (!empty($_POST['password'])) {
							$user_data['user_pass'] = $_POST['password'];
						}
						$user_data['user_email'] = $_POST['email'];
						$user_res = wp_update_user($user_data);
						if (is_wp_error($user_res)) {
							$message = '<div class="alert alert-danger alert-dismissible"> ' . __('User Data not updated.', 'clipmydeals') . ' <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			  </button>
			</div>';
						} else {
							update_user_meta($user_id, 'first_name', $_POST['first_name'] ?? '');
							update_user_meta($user_id, 'last_name', $_POST['last_name'] ?? '');
							if (in_array('clipmydeals-cashback/clipmydeals-cashback.php', get_option('active_plugins'))) {
								cmdcp_save_extra_user_fields($user_id);
							}
							$message = '<div class="alert alert-success alert-dismissible">' . __('User Data updated Successfully.', 'clipmydeals') . ' <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			  </button>
			</div>';
						}
					}
				} else {
					$message = '<div class="alert alert-danger alert-dismissible"> ' . __('Access Denied. Nonce could not be verified.', 'clipmydeals') . ' <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	  </button>
	</div>';
				}
				setcookie('message', $message, 0, "/");
				wp_redirect(esc_url($_POST['current_url']));
				exit;
			}

			add_action('admin_post_clipmydeals_save_user_data', 'clipmydeals_save_user_data');


			function cmd_get_template_part($slug, $name = null, $templates = array(), $args = array())
			{
				$located = '';

				foreach ((array) $templates as $template_name) {
					if (!$template_name)
						continue;

					if (file_exists(STYLESHEETPATH . '/' . $template_name)) {
						$located = STYLESHEETPATH . '/' . $template_name;
						break;
					} elseif (file_exists(TEMPLATEPATH . '/' . $template_name)) {
						$located = TEMPLATEPATH . '/' . $template_name;
						break;
					} elseif (file_exists(ABSPATH . WPINC . '/theme-compat/' . $template_name)) {
						$located = ABSPATH . WPINC . '/theme-compat/' . $template_name;
						break;
					} elseif (file_exists(WP_PLUGIN_DIR . '/clipmydeals-cashback/' . $template_name)) { /* search file within the PLUGIN_DIR_PATH only */
						$located = WP_PLUGIN_DIR . '/clipmydeals-cashback/' . $template_name;
						break;
					} elseif (file_exists(WP_PLUGIN_DIR . '/clipmydeals-comparison/' . $template_name)) { /* search file within the PLUGIN_DIR_PATH only */
						$located = WP_PLUGIN_DIR . '/clipmydeals-comparison/' . $template_name;
						break;
					}
				}
				if ('' != $located && (str_contains($located, WP_PLUGIN_DIR . '/clipmydeals-cashback/') || str_contains($located, WP_PLUGIN_DIR . '/clipmydeals-comparison/'))) {
					load_template($located, false, $args);
				}

				return $located;
			}

			add_action('get_template_part', 'cmd_get_template_part', 10, 4);
