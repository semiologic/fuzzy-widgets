<?php
if ( !class_exists('widget_utils') )
{
	include dirname(__FILE__) . '/widget-utils.php';
}

class fuzzy_widgets_admin
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('fuzzy_widgets_admin', 'meta_boxes'));

		if ( !get_option('sem_links_db_changed') )
		{
			fuzzy_widgets_admin::install();
		}

		if ( get_option('fuzzy_widgets_cache') === false )
		{
			update_option('fuzzy_widgets_cache', array());
		}

		add_action('add_link', array('fuzzy_widgets_admin', 'link_added'));

		if ( version_compare(mysql_get_server_info(), '4.1', '<') )
		{
			add_action('admin_notices', array('fuzzy_widgets_admin', 'mysql_warning'));
			remove_action('widgets_init', array('fuzzy_widgets', 'widgetize'));
		}
	} # init()
	
	
	#
	# mysql_warning()
	#
	
	function mysql_warning()
	{
		echo '<div class="error">'
			. '<p><b style="color: firebrick;">Fuzzy Widgets Error</b><br /><b>Your MySQL version is lower than 4.1.</b> It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.</p>'
			. '</div>';
	} # mysql_warning()


	#
	# install()
	#

	function install()
	{
		global $wpdb;

		$wpdb->query("
			ALTER TABLE `$wpdb->links`
			ADD `link_added` DATETIME
				NOT NULL
				AFTER `link_name`
			");

		$wpdb->query("
			ALTER TABLE `$wpdb->links`
			ADD INDEX ( `link_added` )
			");

		update_option('sem_links_db_changed', 1);

		fuzzy_widgets_admin::link_added();
	} # install()


	#
	# link_added()
	#

	function link_added()
	{
		global $wpdb;

		$wpdb->query("
			UPDATE	$wpdb->links
			SET		link_added = now()
			WHERE	link_added = '0000-00-00 00:00:00'
			");
	} # link_added()
	
	
	#
	# meta_boxes()
	#
	
	function meta_boxes()
	{
		if ( !class_exists('widget_utils') ) return;
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();

		add_action('post_widget_config_affected', array('fuzzy_widgets_admin', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('fuzzy_widgets_admin', 'widget_config_affected'));
	} # meta_boxes()
	
	
	#
	# widget_config_affected()
	#
	
	function widget_config_affected()
	{
		echo '<li>'
			. 'Fuzzy Widgets'
			. '</li>';
	} # widget_config_affected()


	#
	# widget_control()
	#

	function widget_control($widget_args)
	{
		global $wpdb;
		global $post_stubs;
		global $page_stubs;
		global $link_stubs;

		if ( !isset($post_stubs) )
		{
			$post_stubs = (array) $wpdb->get_results("
				SELECT	terms.term_id as value,
						terms.name as label
				FROM	$wpdb->terms as terms
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_id = terms.term_id
				AND		term_taxonomy.taxonomy = 'category'
				WHERE	parent = 0
				ORDER BY terms.name
				");
		}

		if ( !isset($page_stubs) )
		{
			$page_stubs = (array) $wpdb->get_results("
				SELECT	posts.ID as value,
						posts.post_title as label
				FROM	$wpdb->posts as posts
				WHERE	post_parent = 0
				AND		post_type = 'page'
				AND		post_status = 'publish'
				ORDER BY posts.post_title
				");
		}

		if ( !isset($link_stubs) )
		{
			$link_stubs = (array) $wpdb->get_results("
				SELECT	terms.term_id as value,
						terms.name as label
				FROM	$wpdb->terms as terms
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_id = terms.term_id
				AND		term_taxonomy.taxonomy = 'link_category'
				WHERE	parent = 0
				ORDER BY terms.name
				");
		}

		$fuzziness_types = array(
			'days' => __('Days', 'fuzzy-widgets'),
			'days_ago' => __('Days Ago', 'fuzzy-widgets'),
			'items' => __('Items', 'fuzzy-widgets'),
			);

		
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP ); // extract number

		$options = fuzzy_widgets::get_options();

		if ( !$updated && !empty($_POST['sidebar']) )
		{
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();

			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id )
			{
				if ( array('fuzzy_widgets', 'display_widget') == $wp_registered_widgets[$_widget_id]['callback']
					&& isset($wp_registered_widgets[$_widget_id]['params'][0]['number'])
					)
				{
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "fuzzy-widget-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
					
					fuzzy_widgets::clear_cache();
				}
			}

			foreach ( (array) $_POST['fuzzy-widget'] as $num => $opt ) {
				$title = strip_tags(stripslashes($opt['title']));
				$type = $opt['type'];
				$amount = intval($opt['amount']);
				$fuzziness = $opt['fuzziness'];
				$date = isset($opt['date']);
				$desc = isset($opt['desc']);

				if ( !preg_match("/^([a-z_]+)(?:-(\d+))?$/", $type, $match) )
				{
					$type = 'posts';
					$filter = false;
				}
				else
				{
					$type = $match[1];
					$filter = isset($match[2]) ? $match[2] : false;
				}

				if ( $amount <= 0 )
				{
					$amount = 5;
				}

				if ( !in_array($fuzziness, array_keys($fuzziness_types)) )
				{
					$fuzziness = 'days';
				}

				if ( $type == 'comments' )
				{
					$desc = false;
				}

				$options[$num] = compact( 'title', 'type', 'filter', 'amount', 'fuzziness', 'date', 'desc' );
			}

			update_option('fuzzy_widgets', $options);

			$updated = true;
		}

		if ( -1 == $number )
		{
			$ops = fuzzy_widgets::default_options();
			$number = '%i%';
		}
		else
		{
			$ops = $options[$number];
		}

		extract($ops);
		

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="fuzzy-widget-title-' . $number . '">'
			. __('Title', 'fuzzy-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 320px;"'
			. ' id="fuzzy-widget-title-' . $number . '" name="fuzzy-widget[' . $number . '][title]"'
			. ' type="text" value="' . attribute_escape($title) . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="fuzzy-widget-type-' . $number . '">'
			. __('Recent', 'fuzzy-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">';

		$type = $type
			. ( $filter
				? ( '-' . $filter )
				: ''
				);

		echo '<select'
				. ' style="width: 320px;"'
				. ' id="fuzzy-widget-type-' . $number . '" name="fuzzy-widget[' . $number . '][type]"'
				. '>';

		echo '<optgroup label="' . __('Posts', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="posts"'
			. ( $type == 'posts'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Posts', 'fuzzy-widgets') . ' / ' . __('All categories', 'fuzzy-widgets')
			. '</option>';

		foreach ( $post_stubs as $option )
		{
			echo '<option'
				. ' value="posts-' . $option->value . '"'
				. ( $type == ( 'posts-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Posts', 'fuzzy-widgets') . ' / ' . attribute_escape($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Pages', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="pages"'
			. ( $type == 'pages'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Pages', 'fuzzy-widgets') . ' / ' . __('All Parents', 'fuzzy-widgets')
			. '</option>';

		foreach ( $page_stubs as $option )
		{
			echo '<option'
				. ' value="pages-' . $option->value . '"'
				. ( $type == ( 'pages-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Pages', 'fuzzy-widgets') . ' / ' . attribute_escape($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Links', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="links"'
			. ( $type == 'links'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Links', 'fuzzy-widgets') . ' / ' . __('All Categories', 'fuzzy-widgets')
			. '</option>';

		foreach ( $link_stubs as $option )
		{
			echo '<option'
				. ' value="links-' . $option->value . '"'
				. ( $type == ( 'links-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Links', 'fuzzy-widgets') . ' / ' . attribute_escape($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Comments', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="comments"'
			. ( $type == 'comments'
				? 'selected="selected"'
				: ''
				)
			. '>'
			. __('Comments', 'fuzzy-widgets')
			. '</option>';

		echo '</optgroup>';

		echo '<optgroup label="' . __('Updates', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="updates"'
			. ( $type == 'updates'
				? 'selected="selected"'
				: ''
				)
			. '>'
			. __('Updates', 'fuzzy-widgets')
			. '</option>';

		echo '</optgroup>';

		echo '<optgroup label="' . __('Old Posts', 'fuzzy-widgets') . '">'
			. '<option'
			. ' value="old_posts"'
			. ( $type == 'old_posts'
				? 'selected="selected"'
				: ''
				)
			. '>'
			. __('Around This Date In the Past', 'fuzzy-widgets')
			. '</option>';

		echo '</optgroup>';

		echo '</select>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="fuzzy-widget-amount-' . $number . '">'
			. __('Fuzziness', 'fuzzy-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 30px;"'
			. ' id="fuzzy-widget-amount-' . $number . '" name="fuzzy-widget[' . $number . '][amount]"'
			. ' type="text" value="' . $amount . '"'
			. ' />'
			. '&nbsp;'
			. '<select'
				. ' id="fuzzy-widget-fuzziness-' . $number . '" name="fuzzy-widget[' . $number . '][fuzziness]"'
				. '>';

		foreach ( $fuzziness_types as $fuzziness_type => $label )
		{
			echo '<option value="' . $fuzziness_type . '"'
				. ( $fuzziness_type == $fuzziness
					? ' selected="selected"'
					: ''
					)
				. ' >'
				. $label
				. '</option>';
		}

		echo '</select>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="fuzzy-widget-date-' . $number . '">'
			. '<input'
			. ' id="fuzzy-widget-date-' . $number . '" name="fuzzy-widget[' . $number . '][date]"'
			. ' type="checkbox"'
			. ( $date
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Dates', 'fuzzy-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="fuzzy-widget-desc-' . $number . '">'
			. '<input'
			. ' id="fuzzy-widget-desc-' . $number . '" name="fuzzy-widget[' . $number . '][desc]"'
			. ' type="checkbox"'
			. ( $desc
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Descriptions (posts, pages and links)', 'fuzzy-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';
	} # widget_control()
} # fuzzy_widgets_admin

fuzzy_widgets_admin::init();
?>