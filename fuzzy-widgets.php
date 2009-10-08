<?php
/*
Plugin Name: Fuzzy Widgets
Plugin URI: http://www.semiologic.com/software/fuzzy-widgets/
Description: WordPress widgets that let you list recent posts, pages, links, or comments.
Version: 3.0.1
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: fuzzy-widgets
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('fuzzy-widgets', false, dirname(plugin_basename(__FILE__)) . '/lang');

if ( !defined('widget_utils_textdomain') )
	define('widget_utils_textdomain', 'fuzzy-widgets');

if ( !defined('sem_widget_cache_debug') )
	define('sem_widget_cache_debug', false);


/**
 * fuzzy_widget
 *
 * @package Fuzzy Widgets
 **/

class fuzzy_widget extends WP_Widget {
	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		if ( get_option('widget_fuzzy_widget') === false ) {
			foreach ( array(
				'fuzzy_widgets' => 'upgrade',
				) as $ops => $method ) {
				if ( get_option($ops) !== false ) {
					$this->alt_option_name = $ops;
					add_filter('option_' . $ops, array(get_class($this), $method));
					break;
				}
			}
		}
	} # init()
	
	
	/**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include dirname(__FILE__) . '/widget-utils/widget-utils.php';
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();
		add_action('post_widget_config_affected', array('fuzzy_widget', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('fuzzy_widget', 'widget_config_affected'));
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	function widget_config_affected() {
		echo '<li>'
			. __('Fuzzy Widgets', 'fuzzy-widgets')
			. '</li>' . "\n";
	} # widget_config_affected()
	
	
	/**
	 * widgets_init()
	 *
	 * @return void
	 **/

	function widgets_init() {
		register_widget('fuzzy_widget');
	} # widgets_init()
	
	
	/**
	 * fuzzy_widget()
	 *
	 * @return void
	 **/

	function fuzzy_widget() {
		$widget_ops = array(
			'classname' => 'fuzzy_widget',
			'description' => __('Recent Posts, Pages, Links or Comments.', 'fuzzy-widgets'),
			);
		$control_ops = array(
			'width' => 330,
			);
		
		$this->init();
		$this->WP_Widget('fuzzy_widget', __('Fuzzy Widget', 'fuzzy-widgets'), $widget_ops, $control_ops);
	} # fuzzy_widget()
	
	
	/**
	 * widget()
	 *
	 * @param array $args widget args
	 * @param array $instance widget options
	 * @return void
	 **/

	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		$instance = wp_parse_args($instance, fuzzy_widget::defaults());
		extract($instance, EXTR_SKIP);
		
		if ( is_admin() ) {
			echo $before_widget
				. ( $title
					? ( $before_title . $title . $after_title )
					: ''
					)
				. $after_widget;
			return;
		} elseif ( !in_array($type, array('pages', 'posts', 'links', 'comments', 'old_posts', 'updates')) )
			return;
		
		$cache_id = "$widget_id";
		$o = get_transient($cache_id);
		
		if ( !sem_widget_cache_debug && !is_preview() ) {
			if ( $o !== false ) {
				if ( $o )
					echo $o;
				return;
			}
		}
		
		switch ( $type ) {
		case 'pages':
			$items = fuzzy_widget::get_pages($instance);
			break;
		case 'posts':
			$items = fuzzy_widget::get_posts($instance);
			break;
		case 'links':
			$items = fuzzy_widget::get_links($instance);
			break;
		case 'updates':
			$items = fuzzy_widget::get_updates($instance);
			break;
		case 'old_posts':
			$items = fuzzy_widget::get_old_posts($instance);
			break;
		case 'comments':
			$items = fuzzy_widget::get_comments($instance);
			break;
		}
		
		if ( !$items ) {
			if ( !is_preview() )
				set_transient($cache_id, '');
			return;
		}
		
		$title = apply_filters('widget_title', $title);
		
		ob_start();
		
		echo str_replace('fuzzy_widget', 'fuzzy_widget fuzzy_' . $type, $before_widget);
		
		if ( $title )
			echo $before_title . $title . $after_title;
		
		$cur_date = false;
		$prev_date = false;
		$descr = false;
		foreach ( $items as $item ) {
			switch ( $type ) {
			case 'posts':
			case 'pages':
			case 'old_posts':
			case 'updates':
				$label = get_post_meta($item->ID, '_widgets_label', true);
				if ( (string) $label === '' )
					$label = $item->post_title;
				if ( (string) $label === '' )
					$label = __('Untitled', 'fuzzy-widgets');
				
				$link = apply_filters('the_permalink', get_permalink($item->ID));
				
				$label = '<a href="' . esc_url($link) . '"'
						. ' title="' . esc_attr($label) . '"'
						. '>'
					. $label
					. '</a>';
				
				if ( $desc ) {
					$descr = trim(get_post_meta($item->ID, '_widgets_desc', true));
				}
				
				if ( $date ) {
					if ( $type == 'updates' )
						$cur_date = mysql2date(get_option('date_format'), $item->post_modified);
					else
						$cur_date = mysql2date(get_option('date_format'), $item->post_date);
				}
				break;
			case 'links':
				$label = $item->link_name;
				if ( (string) $label === '' )
					$label = __('Untitled', 'fuzzy-widgets');
				
				$label = '<a href="' . esc_url($item->link_url) . '"'
						. ' title="' . esc_attr($label) . '"'
						. '>'
					. $label
					. '</a>';
				
				if ( $desc ) {
					$descr = trim($item->link_description);
				}
				
				if ( $date ) {
					$cur_date = mysql2date(get_option('date_format'), $item->link_added);
					if ( !$cur_date ) {
						if ( $prev_date )
							$cur_date = $prev_date;
						else
							$cur_date = mysql2date(get_option('date_format'), date('H:m:d'));
						
						add_action('shutdown', array('fuzzy_widget', 'activate'));
					}
				}
				break;
			case 'comments':
				$post_label = get_post_meta($item->ID, '_widgets_label', true);
				if ( (string) $post_label === '' )
					$post_label = $item->post_title;
				if ( (string) $post_label === '' )
					$post_label = __('Untitled', 'fuzzy-widgets');
				
				$post_link = apply_filters('the_permalink', get_permalink($item->ID));
				
				$author_label = strip_tags($item->comment_author);
				
				$author_link = $post_link . '#comment-' . $item->comment_ID;
				
				$post_label = '<a href="' . esc_url($post_link) . '"'
						. ' title="' . esc_attr($post_label) . '"'
						. '>'
					. $post_label
					. '</a>';
				
				$author_label = '<a href="' . esc_url($author_link) . '"'
						. ' title="' . esc_attr($author_label) . '"'
						. '>'
					. $author_label
					. '</a>';
				
				$label = sprintf(__('%1$s on %2$s', 'fuzzy-widgets'), $author_label, $post_label);
				
				if ( $date ) {
					$cur_date = mysql2date(get_option('date_format'), $item->comment_date);
				}
				break;
			}
			
			if ( $date && $prev_date && $cur_date != $prev_date ) {
				echo '</ul>' . "\n";
			}
			
			if ( $date && $cur_date != $prev_date ) {
				echo '<h3 class="post_list_date">' . $cur_date . '</h3>' . "\n";
			}
			
			if ( !$date && !$prev_date ) {
				echo '<ul>' . "\n";
				$prev_date = true;
			} elseif ( $date && $cur_date != $prev_date ) {
				echo '<ul>' . "\n";
				$prev_date = $cur_date;
			}
			
			echo '<li>'
				. $label;
				
			if ( $descr )
				echo "\n\n" . wpautop(apply_filters('widget_text', $descr));
			
			echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n";
		
		echo $after_widget;
		
		$o = ob_get_clean();
		
		if ( !is_preview() )
			set_transient($cache_id, $o);
		
		echo $o;
	} # widget()
	
	
	/**
	 * get_pages()
	 *
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_pages($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "FROM	$wpdb->posts as post";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			if ( !get_transient('cached_section_ids') )
				fuzzy_widget::cache_section_ids();
			
			$items_sql .= "
				JOIN	$wpdb->postmeta as meta_filter
				ON		meta_filter.post_id = post.ID
				AND		meta_filter.meta_key = '_section_id'
				AND		meta_filter.meta_value = '$filter'
				";
		}
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type = 'page'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	post.*
				$items_sql
				ORDER BY post.post_date DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	post.*
				$items_sql
				AND		post.post_date >= (
					SELECT MIN( post_day )
					FROM (
						SELECT	CAST(post.post_date AS DATE) as post_day
						$items_sql
						GROUP BY post_day
						ORDER BY post.post_date DESC
						LIMIT $amount
						) as post_days
					)
				ORDER BY post.post_date DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($items_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');

			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_pages()
	
	
	/**
	 * get_posts()
	 *
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_posts($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "FROM	$wpdb->posts as post";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			$items_sql .= "
				JOIN	$wpdb->term_relationships as filter_tr
				ON		filter_tr.object_id = post.ID
				JOIN	$wpdb->term_taxonomy as filter_tt
				ON		filter_tt.term_taxonomy_id = filter_tr.term_taxonomy_id
				AND		filter_tt.term_id = $filter
				AND		filter_tt.taxonomy = 'category'";
		}
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type = 'post'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	post.*
				$items_sql
				ORDER BY post.post_date DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	post.*
				$items_sql
				AND		post.post_date >= (
					SELECT MIN( post_day )
					FROM (
						SELECT	CAST(post.post_date AS DATE) as post_day
						$items_sql
						GROUP BY post_day
						ORDER BY post.post_date DESC
						LIMIT $amount
						) as post_days
					)
				ORDER BY post.post_date DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($items_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');

			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_posts()
	
	
	/**
	 * get_links()
	 *
	 * @param array $instance
	 * @return array $links
	 **/

	function get_links($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "FROM	$wpdb->links as link";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			$items_sql .= "
				JOIN	$wpdb->term_relationships as filter_tr
				ON		filter_tr.object_id = link.link_id
				JOIN	$wpdb->term_taxonomy as filter_tt
				ON		filter_tt.term_taxonomy_id = filter_tr.term_taxonomy_id
				AND		filter_tt.term_id = $filter
				AND		filter_tt.taxonomy = 'link_category'";
		}
		
		$items_sql .= "
				WHERE	link.link_visible = 'Y'";
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	link.*
				$items_sql
				ORDER BY link.link_added DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	link.*
				$items_sql
				AND		link.link_added >= (
					SELECT MIN( link_day )
					FROM (
						SELECT	CAST(link.link_added AS DATE) as link_day
						$items_sql
						GROUP BY link_day
						ORDER BY link.link_added DESC
						LIMIT $amount
						) as link_days
					)
				ORDER BY link.link_added DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$links = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $links === false ) {
			$links = $wpdb->get_results($items_sql);
			wp_cache_add($cache_id, $links, 'widget_queries');
		}
		
		return $links;
	} # get_links()
	
	
	/**
	 * get_updates()
	 *
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_updates($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "FROM	$wpdb->posts as post";
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type IN ( 'post', 'page' )
				AND		post.post_modified > DATE_ADD(post.post_date, INTERVAL 2 DAY)
				AND		widgets_exclude.post_id IS NULL";
		
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	post.*
				$items_sql
				ORDER BY post.post_modified DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	post.*
				$items_sql
				AND		post.post_modified >= (
					SELECT MIN( post_day )
					FROM (
						SELECT	CAST(post.post_modified AS DATE) as post_day
						$items_sql
						GROUP BY post_day
						ORDER BY post.post_modified DESC
						LIMIT $amount
						) as post_days
					)
				ORDER BY post.post_modified DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($items_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');

			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_updates()
	
	
	/**
	 * get_old_posts()
	 *
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_old_posts($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$then = date('Y-m-d', strtotime("-1 year +1 day"));
		
		$items_sql = "FROM	$wpdb->posts as post";
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type IN ( 'post', 'page' )
				AND		post.post_date <= '$then'
				AND		widgets_exclude.post_id IS NULL";
		
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	post.*
				$items_sql
				ORDER BY post.post_date DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	post.*
				$items_sql
				AND		post.post_date >= (
					SELECT MIN( post_day )
					FROM (
						SELECT	CAST(post.post_date AS DATE) as post_day
						$items_sql
						GROUP BY post_day
						ORDER BY post.post_date DESC
						LIMIT $amount
						) as post_days
					)
				ORDER BY post.post_date DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($items_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');

			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_old_posts()
	
	
	/**
	 * get_comments()
	 *
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_comments($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "FROM	$wpdb->posts as post
				JOIN	$wpdb->comments as comment
				ON		comment.comment_post_ID = post.ID";
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type IN ( 'post', 'page' )
				AND		post.post_password = ''
				AND		comment.comment_approved = '1'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $fuzziness == 'items' ) {
			$items_sql = "
				SELECT	post.*,
						comment.*
				$items_sql
				ORDER BY comment.comment_date DESC
				LIMIT $amount
				";
		} else {
			$items_sql = "
				SELECT	post.*,
						comment.*
				$items_sql
				AND		comment.comment_date >= (
					SELECT	MIN(comment_day)
					FROM (
						SELECT	CAST(comment.comment_date AS DATE) as comment_day
						$items_sql
						GROUP BY comment_day
						ORDER BY comment.comment_date DESC
						LIMIT $amount
						) as comment_days
					)
				ORDER BY comment.comment_date DESC
				";
		}
		
		$cache_id = md5($items_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($items_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');

			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_comments()
	
	
	/**
	 * update()
	 *
	 * @param array $new_instance new widget options
	 * @param array $old_instance old widget options
	 * @return array $instance
	 **/

	function update($new_instance, $old_instance) {
		$instance = fuzzy_widget::defaults();
		
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['amount'] = min(max((int) $new_instance['amount'], 1), 10);
		$instance['desc'] = isset($new_instance['desc']);
		$instance['date'] = isset($new_instance['date']);
		
		$type_filter = explode('-', $new_instance['type_filter']);
		$type = array_shift($type_filter);
		$filter = array_pop($type_filter);
		$filter = intval($filter);
		
		$instance['type'] = in_array($type, array('posts', 'pages', 'links', 'comments', 'old_posts', 'updates'))
			? $type
			: 'posts';
		if ( !in_array($instance['type'], array('comments', 'updates')) )
			$instance['filter'] = $filter ? $filter : false;
		else
			$instance['filter'] = false;
		
		if ( !fuzzy_widget::allow_fuzzy() ) {
			$instance['fuzziness'] = 'items';
		} else {
			$instance['fuzziness'] = in_array($new_instance['fuzziness'], array('days', 'items'))
				? $new_instance['fuzziness']
				: 'days';
		}
		
		$instance['amount'] = min($instance['amount'], 10);
		
		fuzzy_widget::flush_cache();
		
		return $instance;
	} # update()
	
	
	/**
	 * form()
	 *
	 * @param array $instance widget options
	 * @return void
	 **/

	function form($instance) {
		$instance = wp_parse_args($instance, fuzzy_widget::defaults());
		static $pages;
		static $categories;
		static $link_categories;
		
		if ( !isset($pages) ) {
			global $wpdb;
			$pages = $wpdb->get_results("
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				WHERE	posts.post_type = 'page'
				AND		posts.post_status = 'publish'
				AND		posts.post_parent = 0
				ORDER BY posts.menu_order, posts.post_title
				");
			update_post_cache($pages);
		}
		
		if ( !isset($categories) ) {
			$categories = get_terms('category', array('parent' => 0));
		}
		
		if ( !isset($link_categories) ) {
			$link_categories = get_terms('link_category', array('parent' => 0));
		}
		
		extract($instance, EXTR_SKIP);
		
		echo '<p>'
			. '<label>'
			. __('Title:', 'fuzzy-widgets') . '<br />' . "\n"
			. '<input type="text" size="20" class="widefat"'
				. ' id="' . $this->get_field_id('title') . '"'
				. ' name="' . $this->get_field_name('title') . '"'
				. ' value="' . esc_attr($title) . '"'
				. ' />'
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. __('Display:', 'fuzzy-widgets') . '<br />' . "\n"
			. '<select name="' . $this->get_field_name('type_filter') . '" class="widefat">' . "\n";
		
		echo '<optgroup label="' . __('Posts', 'fuzzy-widgets') . '">' . "\n"
			. '<option value="posts"' . selected($type == 'posts' && !$filter, true, false) . '>'
			. __('Recent Posts / All Categories', 'fuzzy-widgets')
			. '</option>' . "\n";
		
		foreach ( $categories as $category ) {
			echo '<option value="posts-' . intval($category->term_id) . '"'
					. selected($type == 'posts' && $filter == $category->term_id, true, false)
					. '>'
				. sprintf(__('Recent Posts / %s', 'fuzzy-widgets'), strip_tags($category->name))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Pages', 'fuzzy-widgets') . '">' . "\n"
			. '<option value="pages"' . selected($type == 'pages' && !$filter, true, false) . '>'
			. __('Recent Pages / All Sections', 'fuzzy-widgets')
			. '</option>' . "\n";
		
		foreach ( $pages as $page ) {
			echo '<option value="pages-' . intval($page->ID) . '"'
					. selected($type == 'pages' && $filter == $page->ID, true, false)
					. '>'
				. sprintf(__('Recent Pages / %s', 'fuzzy-widgets'), strip_tags($page->post_label))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Links', 'fuzzy-widgets') . '">' . "\n"
			. '<option value="links"' . selected($type == 'links' && !$filter, true, false) . '>'
			. __('Recent Links / All Categories', 'fuzzy-widgets')
			. '</option>' . "\n";
		
		foreach ( $link_categories as $link_category ) {
			echo '<option value="links-' . intval($link_category->term_id) . '"'
					. selected($type == 'links' && $filter == $link_category->term_id, true, false)
					. '>'
				. sprintf(__('Recent Links / %s', 'fuzzy-widgets'), strip_tags($link_category->name))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Miscellaneous', 'fuzzy-widgets') . '">' . "\n"
			. '<option value="comments"' . selected($type == 'comments', true, false) . '>'
			. __('Recent Comments', 'fuzzy-widgets')
			. '</option>' . "\n"
			. '<option value="old_posts"' . selected($type == 'old_posts', true, false) . '>'
			. __('Around This Date In the Past', 'fuzzy-widgets')
			. '</option>' . "\n"
			. '<option value="updates"' . selected($type == 'updates', true, false) . '>'
			. __('Recent Updates', 'fuzzy-widgets')
			. '</option>' . "\n"
			. '</optgroup>' . "\n";
		
		echo '</select>' . "\n"
			. '</label>'
			. '</p>' . "\n";
		
		if ( fuzzy_widget::allow_fuzzy() ) {
			echo '<p>'
				. '<label>'
				. sprintf(__('%1$s Recent %2$s', 'fuzzy-widgets'),
					'<input type="text" size="3" name="' . $this->get_field_name('amount') . '"'
						. ' value="' . intval($amount) . '"'
						. ' />',
					'<select name="' . $this->get_field_name('fuzziness') . '">' . "\n"
					. '<option value="days"'
						. selected($fuzziness, 'days', false)
						. '>' . __('Days', 'fuzzy-widgets') . '</option>' . "\n"
					. '<option value="items"'
						. selected($fuzziness, 'items', false)
						. '>' . __('Items', 'fuzzy-widgets') . '</option>' . "\n"
					. '</select>'
					)
				. '</label>'
				. '</p>' . "\n";
		} else {
			echo '<p>'
				. '<label>'
				. sprintf(__('%s Recent Items', 'fuzzy-widgets'),
					'<input type="text" size="3" name="' . $this->get_field_name('amount') . '"'
						. ' value="' . intval($amount) . '"'
						. ' />'
					)
				. '</label>'
				. '</p>' . "\n";
		}
		
		echo '<p>'
			. '<label>'
			. '<input type="checkbox" name="' . $this->get_field_name('date') . '"'
				. checked($date, true, false)
				. ' />'
			. '&nbsp;'
			. __('Show Dates', 'fuzzy-widgets')
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="checkbox" name="' . $this->get_field_name('desc') . '"'
				. checked($desc, true, false)
				. ' />'
			. '&nbsp;'
			. __('Show Descriptions (except for comments)', 'fuzzy-widgets')
			. '</label>'
			. '</p>' . "\n";
	} # form()
	
	
	/**
	 * defaults()
	 *
	 * @return array $instance default options
	 **/

	function defaults() {
		$allow_fuzzy = fuzzy_widget::allow_fuzzy();
		
		return array(
			'title' => __('Recent Posts', 'fuzzy-widgets'),
			'type' => 'posts',
			'filter' => false,
			'amount' =>  $allow_fuzzy ? 3 : 5,
			'fuzziness' => $allow_fuzzy ? 'days' : 'items',
			'date' => false,
			'desc' => false,
			);
	} # defaults()
	
	
	/**
	 * save_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function save_post($post_id) {
		$post = get_post($post_id);
		
		if ( $post->post_type != 'page' )
			return;
		
		delete_transient('cached_section_ids');
	} # save_post()
	
	
	/**
	 * cache_section_ids()
	 *
	 * @return void
	 **/

	function cache_section_ids() {
		global $wpdb;
		
		$pages = $wpdb->get_results("
			SELECT	*
			FROM	$wpdb->posts
			WHERE	post_type = 'page'
			");
		
		update_post_cache($pages);
		
		$to_cache = array();
		foreach ( $pages as $page )
			$to_cache[] = $page->ID;
		
		update_postmeta_cache($to_cache);
		
		foreach ( $pages as $page ) {
			$parent = $page;
			while ( $parent->post_parent )
				$parent = get_post($parent->post_parent);
			
			if ( "$parent->ID" !== get_post_meta($page->ID, '_section_id', true) )
				update_post_meta($page->ID, '_section_id', "$parent->ID");
		}
		
		set_transient('cached_section_ids', 1);
	} # cache_section_ids()
	
	
	/**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( !get_option('sem_links_db_changed') ) {
			global $wpdb;

			$wpdb->query("
				ALTER TABLE `$wpdb->links`
				ADD `link_added` DATETIME NOT NULL AFTER `link_name`
				");

			$wpdb->query("
				ALTER TABLE `$wpdb->links`
				ADD INDEX ( `link_added` )
				");

			update_option('sem_links_db_changed', 1);
		}

		fuzzy_widget::link_added();
		fuzzy_widget::flush_cache();
	} # activate()
	
	
	/**
	 * link_added()
	 *
	 * @return void
	 **/

	function link_added() {
		global $wpdb;

		$wpdb->query("
			UPDATE	$wpdb->links
			SET		link_added = NOW()
			WHERE	link_added = '0000-00-00 00:00:00'
			");
	} # link_added()
	
	
	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function flush_cache($in = null) {
		$cache_ids = array();
		
		$widgets = get_option("widget_fuzzy_widget");
		
		if ( !$widgets )
			return $in;
		
		unset($widgets['_multiwidget']);
		unset($widgets['number']);
		
		foreach ( array_keys($widgets) as $widget_id )
			$cache_ids[] = "fuzzy_widget-$widget_id";
		
		foreach ( $cache_ids as $cache_id )
			delete_transient($cache_id);
		
		return $in;
	} # flush_cache()
	
	
	/**
	 * allow_fuzzy()
	 *
	 * @return bool $allow_fuzzy
	 **/

	function allow_fuzzy() {
		static $allow_fuzzy;
		
		if ( isset($allow_fuzzy) )
			return $allow_fuzzy;
		
		global $wpdb;
		$allow_fuzzy = version_compare($wpdb->db_version(), '4.1', '>=');
		
		return $allow_fuzzy;
	} # allow_fuzzy()
	
	
	/**
	 * upgrade()
	 *
	 * @param array $ops
	 * @return array $ops
	 **/

	function upgrade($ops) {
		$widget_contexts = class_exists('widget_contexts')
			? get_option('widget_contexts')
			: false;
		
		foreach ( $ops as $k => $o ) {
			if ( isset($widget_contexts['fuzzy-widget-' . $k]) ) {
				$ops[$k]['widget_contexts'] = $widget_contexts['fuzzy-widget-' . $k];
			}
		}
		
		if ( is_admin() ) {
			$sidebars_widgets = get_option('sidebars_widgets', array('array_version' => 3));
		} else {
			if ( !$GLOBALS['_wp_sidebars_widgets'] )
				$GLOBALS['_wp_sidebars_widgets'] = get_option('sidebars_widgets', array('array_version' => 3));
			$sidebars_widgets =& $GLOBALS['_wp_sidebars_widgets'];
		}
		
		$keys = array_keys($ops);
		
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ( !is_array($widgets) )
				continue;
			foreach ( $keys as $k ) {
				$key = array_search("fuzzy-widget-$k", $widgets);
				if ( $key !== false ) {
					$sidebars_widgets[$sidebar][$key] = 'fuzzy_widget-' . $k;
					unset($keys[array_search($k, $keys)]);
				}
			}
		}
		
		if ( is_admin() )
			update_option('sidebars_widgets', $sidebars_widgets);
		
		return $ops;
	} # upgrade()
} # fuzzy_widget

add_action('widgets_init', array('fuzzy_widget', 'widgets_init'));

foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php') as $hook )
	add_action('load-' . $hook, array('fuzzy_widget', 'editor_init'));

foreach ( array(
		'save_post',
		'delete_post',
		'switch_theme',
		'update_option_active_plugins',
		'update_option_show_on_front',
		'update_option_page_on_front',
		'update_option_page_for_posts',
		'update_option_sidebars_widgets',
		'update_option_sem5_options',
		'update_option_sem6_options',
		'generate_rewrite_rules',
		
		'add_link',
		'edit_link',
		'delete_link',
		'edit_comment',
		'comment_post',
		'wp_set_comment_status',
		
		'flush_cache',
		'after_db_upgrade',
		) as $hook)
	add_action($hook, array('fuzzy_widget', 'flush_cache'));

register_activation_hook(__FILE__, array('fuzzy_widget', 'activate'));
register_deactivation_hook(__FILE__, array('fuzzy_widget', 'flush_cache'));

add_action('save_post', array('fuzzy_widget', 'save_post'));
add_action('add_link', array('fuzzy_widget', 'link_added'));

wp_cache_add_non_persistent_groups(array('widget_queries'));
?>