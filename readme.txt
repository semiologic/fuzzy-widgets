=== Fuzzy Widgets ===
Contributors: Denis-de-Bernardy
Donate link: http://www.semiologic.com/partners/
Tags: semiologic
Requires at least: 2.8
Tested up to: 3.0
Stable tag: trunk

A collection of widgets to list recent posts, pages, comments, and more.


== Description ==

The Fuzzy Widgets plugin for WordPress introduces multi-use widgets that allow you to list recent posts, pages, updates, comments, links, as well as a list of posts from the past.

To use the plugin, browse Appearance / Widgets, insert a Fuzzy Widget where you want it to be, and configure it as appropriate.

- When displaying recent posts, pages or links, you can optionally filter the results by category or section.
- Recent updates will display recently updated pages.
- Recent comments will only display comments from public posts and pages -- fully ignoring password protected and private content.
- Recent posts from the past will display posts that were published around a year ago.

= What the fuzziness is about =

The plugin originally earned its name from fuzzy logic. Fuzzy logic is a field that is generally associated with artificial intelligence, in which things are not either true or false, but rather somewhat true and false at the same time.

Anyway, in an earliest versions, the plugin sought to guess the amount of posts to display based on the speed at which you're updating your site. It assume you wanted to output a user-specified number of items, but then took the liberty to display more or less based on what's happening on the site.

Specifically, this site had (and still has) an irregular stream of posts. I wanted a plugin that knew better than to always display 10 posts: it ought to stick to 3 when I'm posting once per month, and a few more when there are spikes of activity -- e.g. the three from yesterday, and two more from the last two months.

Part of the original idea survived, but with a more straightforward algorithm. Since 3.0, you can decide to have the widgets display either a fixed number of days worth of posts, or a fixed number of items.

A fixed number of items makes that widget behave like the Recent Posts and Comments widgets that are built into WordPress, except that it isn't loop breaking -- i.e. it won't turn your site into a train wreck if placed in the loop.

A fixed number of days worth of posts is fuzzier, and means exactly that: if the last three days you posted were yesterday, a week ago, and a month ago, it'll display posts from those last three days. You'd then get a few posts from several dates, but not so many posts that year-old posts show up as recent on a low volume blog.

= This post/page in widgets =

This plugin shares options with a couple of other plugins from Semiologic. They're available when editing your posts and pages, in meta boxes called "This post in widgets" and "This page in widgets."

These options allow you to configure a title and a description that are then used by Fuzzy Widgets, Random Widgets, Related Widgets, Nav Menu Widgets, Silo Widgets, and so on. They additionally allow you to exclude a post or page from all of them in one go.

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 3.0.5 =

- WP 3.0 compat

= 3.0.4 =

- Further cache improvements (fix priority)

= 3.0.3 =

- Cache improvements

= 3.0.2 =

- WP 2.9 compat

= 3.0.1 =

- Fix the fuzzy comments query

= 3.0 =

- Complete rewrite
- WP_Widget class
- Localization
- Code enhancements and optimizations
