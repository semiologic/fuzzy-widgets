<?php
/*
Plugin Name: Fuzzy Widgets
Plugin URI: http://www.semiologic.com/software/widgets/fuzzy-widgets/
Description: WordPress widgets that let you list fuzzy numbers of posts, pages, links, or comments.
Author: Denis de Bernardy
Version: 2.2.1 RC
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/plugins
Update Tag: fuzzy_widgets
Update Package: http://www.semiologic.com/media/software/widgets/fuzzy-widgets/fuzzy-widgets.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('fuzzy-widgets','wp-content/plugins/fuzzy-widgets');

class fuzzy_widgets
{
	#
	# init()
	#

	function init()
	{
		add_action('widgets_init', array('fuzzy_widgets', 'widgetize'));

		foreach ( array(
				'save_post',
				'delete_post',
				'add_link',
				'edit_link',
				'delete_link',
				'edit_comment',
				'comment_post',
				'wp_set_comment_status',
				'switch_theme',
				'update_option_show_on_front',
				'update_option_page_on_front',
				'update_option_page_for_posts',
				'generate_rewrite_rules',
				) as $hook)
		{
			add_action($hook, array('fuzzy_widgets', 'clear_cache'));
		}
		
		register_activation_hook(__FILE__, array('fuzzy_widgets', 'clear_cache'));
		register_deactivation_hook(__FILE__, array('fuzzy_widgets', 'clear_cache'));
	} # init()


	#
	# widgetize()
	#

	function widgetize()
	{
		$options = fuzzy_widgets::get_options();
		
		$widget_options = array('classname' => 'fuzzy_widget', 'description' => __( "A fuzzy number of recent posts, pages, links or comments") );
		$control_options = array('width' => 500, 'id_base' => 'fuzzy-widget');
		
		$id = false;

		# registered widgets
		foreach ( array_keys($options) as $o )
		{
			if ( !is_numeric($o) ) continue;
			$id = "fuzzy-widget-$o";

			wp_register_sidebar_widget($id, __('Fuzzy Widget'), array('fuzzy_widgets', 'display_widget'), $widget_options, array( 'number' => $o ));
			wp_register_widget_control($id, __('Fuzzy Widget'), array('fuzzy_widgets_admin', 'widget_control'), $control_options, array( 'number' => $o ) );
		}
		
		# default widget if none were registered
		if ( !$id )
		{
			$id = "fuzzy-widget-1";
			wp_register_sidebar_widget($id, __('Fuzzy Widget'), array('fuzzy_widgets', 'display_widget'), $widget_options, array( 'number' => -1 ));
			wp_register_widget_control($id, __('Fuzzy Widget'), array('fuzzy_widgets_admin', 'widget_control'), $control_options, array( 'number' => -1 ) );
		}
		
		# kill recent posts and recent comments widgets
		global $wp_registered_widgets;
		global $wp_registered_widget_controls;

		foreach ( array('recent-posts', 'recent-comments') as $widget_id )
		{
			unset($wp_registered_widgets[$widget_id]);
			unset($wp_registered_widget_controls[$widget_id]);
		}
	} # widgetize()


	#
	# display_widget()
	#

	function display_widget($args, $widget_args = 1)
	{
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		$number = intval($number);

		# front end: serve cache if available
		if ( !is_admin() )
		{
			$cache = get_option('fuzzy_widgets_cache');

			if ( isset($cache[$number]) )
			{
				echo $cache[$number];
				return;
			}
		}

		# get options
		$options = fuzzy_widgets::get_options();
		$options = $options[$number];
		
		# admin area: serve a formatted title
		if ( is_admin() )
		{
			echo $args['before_widget']
				. $args['before_title'] . $options['title'] . $args['after_title']
				. $args['after_widget'];

			return;
		}
		
		# initialize
		$o = '';

		# fetch items
		switch ( $options['type'] )
		{
		case 'posts':
			$items = fuzzy_widgets::get_posts($options);
			break;

		case 'old_posts':
			$items = fuzzy_widgets::get_old_posts($options);
			break;

		case 'pages':
			$items = fuzzy_widgets::get_pages($options);
			break;

		case 'links':
			$items = fuzzy_widgets::get_links($options);
			break;

		case 'comments':
			$items = fuzzy_widgets::get_comments($options);
			break;

		case 'updates':
			$items = fuzzy_widgets::get_updates($options);
			break;

		default:
			$items = array();
			break;
		}

		# fetch output
		if ( $items )
		{
			$o .= $args['before_widget'] . "\n"
				. ( $options['title']
					? ( $args['before_title'] . $options['title'] . $args['after_title'] . "\n" )
					: ''
					);

			if ( !$options['date'] )
			{
				$o .= '<ul>' . "\n";
			}

			foreach ( $items as $item )
			{
				if ( $options['date'] )
				{
					$cur_date = mysql2date(get_option('date_format'), $item->item_date);

					if ( !isset($prev_date) )
					{
						$o .= '<h3>' . $cur_date . '</h3>' . "\n"
							. '<ul>' . "\n";
					}
					elseif ( $cur_date != $prev_date )
					{
						$o .= '</ul>' . "\n"
							. '<h3>' . $cur_date . '</h3>' . "\n"
							. '<ul>' . "\n";
					}

					$prev_date = $cur_date;
				}

				$o .= '<li>'
					. $item->item_label
					. '</li>' . "\n";
			}

			$o .= '</ul>' . "\n";

			$o .= $args['after_widget'] . "\n";
		}

		# cache
		$cache[$number] = $o;

		update_option('fuzzy_widgets_cache', $cache);

		# display
		echo $o;
	} # display_widget()


	#
	# get_posts()
	#

	function get_posts($options)
	{
		global $wpdb;
		
		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc,
					posts.post_date as item_date
			FROM	$wpdb->posts as posts
			"
			. ( $options['filter']
				? ( "
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = posts.ID
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			AND		term_taxonomy.taxonomy = 'category'
			AND		term_taxonomy.term_id = " . intval($options['filter'])
			)
				: ''
				)
			. "
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'post'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )
			"
			;

		$items = fuzzy_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_posts()


	#
	# get_old_posts()
	#

	function get_old_posts($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc,
					posts.post_date as item_date
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'post'
			AND		posts.post_password = ''
			AND		posts.post_date <= now() - interval 1 year
			AND		posts.ID NOT IN ( $exclude_sql )
			"
			;

		$items = fuzzy_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_old_posts()


	#
	# get_pages()
	#

	function get_pages($options)
	{
		global $wpdb;
		global $page_filters;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		if ( $options['filter'] )
		{
			if ( isset($page_filters[$options['filter']]) )
			{
				$parents_sql = $page_filters[$options['filter']];
			}
			else
			{
				$parents = array($options['filter']);

				do
				{
					$old_parents = $parents;

					$parents_sql = implode(', ', $parents);

					$parents = (array) $wpdb->get_col("
						SELECT	posts.ID
						FROM	$wpdb->posts as posts
						WHERE	posts.post_status = 'publish'
						AND		posts.post_type = 'page'
						AND		( posts.ID IN ( $parents_sql ) OR posts.post_parent IN ( $parents_sql ) )
						");
					
					sort($parents);
				} while ( $parents != $old_parents );

				$page_filters[$options['filter']] = $parents_sql;
			}
		}
		
		#dump($parents_sql);
		
		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc,
					posts.post_date as item_date
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'page'
			AND		posts.post_password = ''
			"
			. ( $options['filter']
				? ( "
			AND		posts.post_parent IN ( $parents_sql )
			" )
				: ''
				)
			. "
			AND		posts.ID NOT IN ( $exclude_sql )
			"
			;
		
		#dump($items_sql);
		
		$items = fuzzy_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_pages()


	#
	# get_links()
	#

	function get_links($options)
	{
		global $wpdb;

		$items_sql = "
			SELECT	links.*,
					links.link_added as item_date
			FROM	$wpdb->links as links
			"
			. ( $options['filter']
				? ( "
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = links.link_id
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			AND		term_taxonomy.taxonomy = 'link_category'
			AND		term_taxonomy.term_id = " . intval($options['filter'])
			)
				: ''
				)
			. "
			WHERE	links.link_visible = 'Y'
			"
			;

		$items = fuzzy_widgets::get_items($items_sql, $options);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars($items[$key]->link_url)
				. '">'
				. $items[$key]->link_name
				. '</a>'
				. ( $options['desc'] && $items[$key]->link_description
					? wpautop($items[$key]->link_description)
					: ''
					);
		}

		return $items;
	} # get_links()


	#
	# get_comments()
	#

	function get_comments($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$min_comment_date_sql = "
				SELECT	comment_post_ID,
						max(comment_date) as min_comment_date
				FROM	$wpdb->comments
				WHERE	comment_ID NOT IN (
					SELECT	invalid_comments.comment_ID
					FROM	$wpdb->comments as invalid_comments
					INNER JOIN (
						SELECT	comment_post_ID,
								max(comment_date) as max_comment_date
						FROM	$wpdb->comments
						GROUP BY comment_post_ID
						HAVING	count(comment_ID) > 1
						) as latest_comments
					ON		latest_comments.comment_post_ID = invalid_comments.comment_post_ID
					WHERE	invalid_comments.comment_date = latest_comments.max_comment_date
					)
				GROUP BY comment_post_ID
				";

		$items_sql = "
			SELECT	posts.*,
					comments.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					comments.comment_date as item_date
			FROM	$wpdb->posts as posts
			INNER JOIN $wpdb->comments as comments
			ON		comments.comment_post_ID = posts.ID
			INNER JOIN ( $min_comment_date_sql ) as valid_comments
			ON		valid_comments.comment_post_ID = comments.comment_post_ID
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type IN ('post', 'page')
			AND		posts.post_password = ''
			AND		comments.comment_approved = '1'
			AND		comments.comment_date >= valid_comments.min_comment_date
			AND		posts.ID NOT IN ( $exclude_sql )
			"
			;

		$items = fuzzy_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = $items[$key]->comment_author
				. ' ' . __('on', 'fuzzy-widgets') . ' '
				. '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)) . '#comment-' . $items[$key]->comment_ID)
				. '">'
				. $items[$key]->post_label
				. '</a>';
		}

		return $items;
	} # get_comments()


	#
	# get_updates()
	#

	function get_updates($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";

		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc,
					posts.post_modified as item_date
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type IN ('post', 'page')
			AND		posts.post_password = ''
			AND		posts.post_modified > DATE_ADD(posts.post_date, INTERVAL 2 DAY)
			AND		posts.ID NOT IN ( $exclude_sql )
			"
			;

		$items = fuzzy_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_updates()


	#
	# get_items()
	#

	function get_items($items_sql, $options)
	{
		global $wpdb;

		switch ( $options['fuzziness'] )
		{
		case 'days':
			$min_item_date_sql = "
				SELECT	MIN(min_item_date) as min_item_date
				FROM (
					SELECT	DISTINCT DATE_FORMAT( item_date, '%Y-%m-%d 00:00:00' ) as min_item_date
					FROM	( $items_sql ) as items
					ORDER BY min_item_date DESC
					LIMIT " . intval($options['amount']) . "
					) as min_item_dates
				";

			$items = (array) $wpdb->get_results("
				SELECT	items.*
				FROM	( $items_sql ) as items
				WHERE	items.item_date >= ( $min_item_date_sql )
				ORDER BY items.item_date DESC"
				);
			break;

		case 'days_ago':
			$items = (array) $wpdb->get_results("
				SELECT	items.*
				FROM	( $items_sql ) as items
				WHERE	items.item_date >= now() - interval " . intval($options['amount']) . " day
				ORDER BY items.item_date DESC"
				);
			break;

		case 'items':
			$items = (array) $wpdb->get_results("
				SELECT	items.*
				FROM	( $items_sql ) as items
				ORDER BY items.item_date DESC
				LIMIT " . intval($options['amount'])
				);
			break;

		default:
			$items = array();
		}

		return $items;
	} # get_items()


	#
	# clear_cache()
	#

	function clear_cache($in = null)
	{
		update_option('fuzzy_widgets_cache', array());

		return $in;
	} # clear_cache()


	#
	# get_options()
	#

	function get_options()
	{
		if ( ( $o = get_option('fuzzy_widgets') ) === false )
		{
			$o = array();

			update_option('fuzzy_widgets', $o);
		}

		return $o;
	} # get_options()
	
	
	#
	# new_widget()
	#
	
	function new_widget()
	{
		$o = fuzzy_widgets::get_options();
		$k = time();
		do $k++; while ( isset($o[$k]) );
		$o[$k] = fuzzy_widgets::default_options();
		
		update_option('fuzzy_widgets', $o);
		
		return 'fuzzy-widget-' . $k;
	} # new_widget()


	#
	# default_options()
	#

	function default_options()
	{
		return array(
			'title' => __('Recent Posts'),
			'type' => 'posts',
			'amount' => 5,
			'fuzziness' => 'days',
			'date' => false,
			'desc' => false,
			);
	} # default_options()
} # fuzzy_widgets

fuzzy_widgets::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/fuzzy-widgets-admin.php';
}
?>