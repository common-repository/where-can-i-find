<?php
/*
Plugin Name: Where Can I Find 
Plugin URI: http://www.vagabumming.com/where-can-i-find/
Description: The "Where Can I Find" list for expat websites.  Shortcodes: [wcifsearch] displays the list with search form.  [wcifadditem] displays the form that allows users to add items.  Configure options in the wordpress dashboard under Settings/Where can I find. 
Version: 2.9
Author: Will Brubaker
Author URI: http://www.vagabumming.com
License: GPL2
*/
/*  Copyright 2011  Will Brubaker  (email : will@vagabumming.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class where_can_i_find {
static private $vagabumming_plugin_values = array( 
											'name'=>'Where Can I Find',
											'version'=>'2.9', //hate using a string value here, but need it to hold non-numeric values
											'slug'=>'wcif',
											'dbversion'=>'1.0.1',
											'supplementary'=>array(//a place to put things in the future..
																)
											);
function where_can_i_find(){
global $wpdb;

//check current plugin version + db version against installed versions, if either don't match, run initialize_plugin
if(get_option('vagabumming_' . self::$vagabumming_plugin_values['slug'] . '_plugin_version') < self::$vagabumming_plugin_values['version'] || get_option('wcif_db_version') < self::$vagabumming_plugin_values['dbversion'])
	self::initialize_plugin();
add_action('wp_footer', array(&$this,'load_wcif_deps'));
add_action('init',array(&$this,'register_wcif_deps'));
add_action('wp_print_styles',array(&$this,'load_stylesheets'));
add_action('wp_ajax_nopriv_myajaxregistration', array(&$this,'ajax_my_registration') );
add_action('wp_ajax_wcifsearch', array(&$this,'wcifsearchquery'));
add_action('wp_ajax_nopriv_wcifsearch',array(&$this,'wcifsearchquery'));
add_action('admin_menu',array(&$this,'admin_menu'));
add_shortcode('wcifsearch',array(&$this,'wcif_search'));
add_shortcode('wcifadditem',array(&$this,'wcif_additem'));
add_filter('pre_get_posts', array(&$this,'query_post_type'));
//message function needs to run unless several conditions are met.  It traps the 'set linkbacks' so, let's run it always
add_action('admin_notices', array(&$this,'message'));
register_deactivation_hook(__FILE__,array(&$this,'remove_plugin'));
add_filter('plugin_action_links',array(&$this,'my_plugin_action_links'),10,2);

}
function register_wcif_deps(){
wp_register_script('tiptip', plugins_url('js/jquery.tipTip.minified.js', __FILE__), array('jquery'));
wp_register_script('jpajinate', plugins_url('js/jquery.pajinate.js', __FILE__), array('jquery'));
wp_register_script('jquery-effects', plugins_url('js/jquery-ui-effects-min.js', __FILE__), array('jquery', 'jquery-ui-core'));

$taxonomies = array( 0 => 'category', 1 => 'post_tag');
$args = array(
	'public' => true,
	'label' => 'where can i find',
	'has_archive' => true,
	'hierarchical' => true,
	'taxonomies' => $taxonomies
	);
register_post_type('where-can-i-find', $args);
}
function load_stylesheets(){
wp_enqueue_style('wcifstylesheet',plugins_url('css/wcif.css', __FILE__));
wp_enqueue_style('tiptip', plugins_url('css/tipTip.css', __FILE__));
wp_enqueue_script('wcif_registration', plugins_url('js/wcif_registration.min.js', __FILE__), array('jquery', 'jquery-ui-core', 'jquery-effects'),1.0, true); 
wp_localize_script('wcif_registration', 'wcif_ajax',array(aJaxURL => admin_url('admin-ajax.php')));
}
function load_wcif_deps(){
global $vagabumming_link_back, $wcif_deps_loaded;
if(!$vagabumming_link_back){
	if(is_front_page()){
	echo '<div id=vagabumming_link_back style="text-align: center; margin: 0px, auto;"><a href="http://www.vagabumming.com"	>Global Travel</a></div>';
		$vagabumming_link_back = true;
		}
	}
if(! $wcif_deps_loaded)
	return;
wp_print_scripts('wp-ajax-response');
}
function initialize_plugin(){
//if this isn't a public blog on a public server, don't call home:
if((get_option('blog_public') == 1) && filter_var($_SERVER['SERVER_ADDR'],FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
	$this_site = urlencode(get_site_url());
	$this_plugin = urlencode(self::$vagabumming_plugin_values['name']);
	$url = 'http://www.vagabumming.com/mypluginusers.php?plugin_name=' . $this_plugin . '&site=' . $this_site;
	file_get_contents($url);
}

$installed_plugins = get_option('vagabumming_plugins_installed');
$plugin_name = self::$vagabumming_plugin_values['name'];
if(!in_array($plugin_name,$installed_plugins)){
	$installed_plugins[] = $plugin_name;
	update_option('vagabumming_plugins_installed',$installed_plugins);
	}

//give plugin users another chance to show the love!
if(get_option('vagabumming_link_back') == 'nolinkback') delete_option('vagabumming_link_back');
//put the new version information in the db to keep this function from ever running again
update_option('vagabumming_' . self::$vagabumming_plugin_values['slug'] . '_plugin_version',self::$vagabumming_plugin_values['version']);
		$dbVersion = "1.0.1";		
		$installed_ver = get_option( "wcif_db_version" );
		global $wpdb;

		if ($installed_ver < self::$vagabumming_plugin_values['dbversion'] ) {
		
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
			$table_name = $wpdb->prefix . "wcif";
			$table_staticvalues = $wpdb->prefix . "wcif_staticvalues";
			
			$sql = "CREATE TABLE " . $table_name . " (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `wcif_item` text CHARACTER SET utf8 NOT NULL,
  `wcif_location` text CHARACTER SET utf8 NOT NULL,
  `wcif_category` text CHARACTER SET utf8 NOT NULL,
  `wcif_details` text CHARACTER SET utf8 NOT NULL,
  `contributor` text CHARACTER SET utf8 NOT NULL,
  `url` varchar(255) CHARACTER SET utf8 NOT NULL,
  `wcif_locationdetails` text CHARACTER SET utf8,
  UNIQUE KEY `id` (`id`)
);";
			$wpdb->query($sql);
	
		    update_option("wcif_db_version", $dbVersion);
		}
		if(! get_option('wcif_city') ){
		    	update_option('wcif_city','your City, Country goes here');
		    	}
		$wcif_categories = array('Grocery', 'Services', 'Food/Drink', 'Other/Uncategorized', 'Consumer Goods');
		if(! get_option('wcif_categories')){
			update_option('wcif_categories',$wcif_categories);
			}
		if(get_cat_ID('wcif') == 0){
			wp_create_category('wcif');
			}
	
}
function query_post_type($query) {
  if(is_category() || is_tag()) {
    $post_type = get_query_var('post_type');
	if($post_type)
	    $post_type = $post_type;
	else
	    $post_type = array('post','where-can-i-find'); // replace cpt to your custom post type
    $query->set('post_type',$post_type);
	return $query;
    }
}
function admin_menu(){
add_options_page('where can i find', 'Where can I find', 'manage_options','wcif',array(&$this,'set_plugin_options'));
}
function message($msg){
$msg1 = '<p>Thank you for using the \'' . self::$vagabumming_plugin_values['name'] . '\' plugin.  You now have The ability to drive traffic to your site with an <strong>AWESOME</strong> directory of goods &amp; services.  To say thanks for these powers, Please <a title="set a link back to the plugin developer\'s site.  Only shows on your home page" href="?set_link=yes">link back</a> to the developer\'s site (only shows a link on pages where this plugin runs).  Of course you can use this code without linking back, freeloading is o.k., nobody will know. <a href="?set_link=no">I\'m a freeloader and don\'t want to link back</a>.  (either option will make this message go away)</p>';
$msg2 = '<p>By default, sites using this plugin will be linked from the developer\'s site.  You can, of course, <a href="?opt_out">opt out</a> of this option.</p>';
	if(isset($msg)){
	if($msg == 1){
		$msg = $msg1;
		}elseif($msg == 2){
		$msg = $msg2;
		}
	return $msg . "\n";
	}
	}
function my_plugin_action_links($links,$file){
static $this_plugin;
	if(! $this_plugin) {
		$this_plugin = plugin_basename(__FILE__);
		}
	if($file == $this_plugin){
		if(get_option('vagabumming_link_back') == 'linkback'){
			$link = '<a title="remove the link to the developer\'s page" href="?set_link=no">Remove Linkback</a><br />';
			}
			else{
			$link = '<a title="set a link from pages where this plugin runs to the developer\'s page <3" href="?set_link=yes">Set Linkback</a><br />';
			}
		array_unshift($links, $link);
	$hire_me_link = '<a title="hire me for custom wordpress development needs" href="http://www.vagabumming.com/hire-me-for-your-website-development-needs/">Hire Me!</a>';
	array_unshift($links, $hire_me_link);
	}
return $links;
}
function remove_plugin(){
delete_option('vagabumming_' . self::$vagabumming_plugin_values['slug'] . '_plugin_version');
$x = count(get_option('vagabumming_plugins_installed'));
	if($x <= 1){//this is the last (or only) vagabumming plugin installed, remove ALL traces of vagabumming plugins
		delete_option('vagabumming_opt_out');
		delete_option('vagabumming_plugins_installed');
		delete_option('vagabumming_link_back');
		delete_option('wcif_city');
		delete_option('wcif_categories');
	}else{//this plugin is the only one we're uninstalling.  Let's just remove it from the array.
	$plugins_installed = get_option('vagabumming_plugins_installed');
	foreach($plugins_installed as $plugin){
		if ($plugin != self::$vagabumming_plugin_values['name']){
			$tmp[] = $plugin;
			}
		}
	update_option('vagabumming_plugins_installed',$tmp);
	}

}
function set_plugin_options(){
if (!current_user_can('manage_options'))
    {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }
global $wpdb;
$wcif_categories = array('Grocery', 'Services', 'Food/Drink', 'Other/Uncategorized', 'Consumer Goods');
$wcif_city = get_option('wcif_city');

    if(! get_option('vagabumming_link_back') && !isset($_REQUEST['set_link'])){
	$msg = 1;
	echo '<div id="message" class="updated">' . self::message($msg) . '</div>';
	}
	if(get_option('blog_public') == 1 && !get_option('vagabumming_opt_out')){
		
		if(!isset($_REQUEST['opt_out'])){
			$msg = 2;
			echo '<div id="message" class="updated">' . self::message($msg) . '</div>';
			}
		}
	if(isset($_REQUEST['opt_out'])){
		$this_site = urlencode(get_site_url());
		$url = 'http://www.vagabumming.com/mypluginusers.php?opt_out&site=' . $this_site;
		file_get_contents($url);
		update_option('vagabumming_opt_out',1);
		}
	if(isset($_REQUEST['opt_out'])){
		$this_site = urlencode(get_site_url());
		$url = 'http://www.vagabumming.com/mypluginusers.php?opt_out&site=' . $this_site;
		file_get_contents($url);
		update_option('vagabumming_opt_out',1);
		}
    if(isset($_POST['wcifupdatecity']) && ($_POST['wcifupdatecity'] == 'Y') && ($_POST['wcifcity'] != '')){
    $nonce= $_POST['updatecitynonce'];
    if(! wp_verify_nonce($nonce,'updatecitynonce')) die('not authorized');
	$newcity = $_POST['wcifcity'];
	update_option('wcif_city',$newcity);
	}
	unset($wcif_city);
	$wcif_city = get_option('wcif_city');
	if(isset($_POST['wcifupdateoptions']) && ($_POST['wcifupdateoptions'] == 'Y')){
	$nonce = $_POST['updateoptionnonce'];
	if(! wp_verify_nonce($nonce,'updateoptionnonce')) die('not authorized');
	$orig_categories = get_option('wcif_categories');
	$rm_categories = $_POST['categories'];
		foreach($rm_categories as $i){
			unset($orig_categories[$i]);
		}
		foreach($orig_categories as $category){
			$keep_categories[] = $category;
			}
		update_option('wcif_categories',$keep_categories);			
	}
	if(isset($_POST['wcifaddcategory']) && ($_POST['wcifaddcategory'] == 'Y')){
		$nonce = $_POST['updatecategorynonce'];
		if(! wp_verify_nonce($nonce,'updatecategorynonce')) die('not authorized');
		$newcategory = $_POST['wcifcategory'];
		$wcif_categories = get_option('wcif_categories');
		$wcif_categories[] = $newcategory;
		update_option('wcif_categories',$wcif_categories);
	}
?>
<div id="message" class="widefat" style="padding: 0.6em; margin-right: 0.6em; width: 95%;">
<p>This is the 'Where Can I Find' plugin version <?php echo self::$vagabumming_plugin_values['version'] ?>, designed primarily with expat websites/blogs in mind.</p>
<h3>What it does, how it works</h3>
<p>If you are running a website for expats in a particular city/region this can be a valuable tool for people who are looking for those little (or big) items that will
make their lives easier.
</p>  
<p>The list displays 3 columns of information.  The first column is a category (which can be customized below) the second is an item name
and the third is the location.  The item name and location can also display optional information via a tooltip that is activated if the optional information
is entered.  This list is displayed by putting the shortcode [wcifsearch] in a page or post.  This plugin was designed with the intention of
having no other content within the page or post that displays the list, but other content should work just fine.</p>
<h3>Adding Items</h3>
<p>Create a page or post with the shortcode [wcifadditem] to create the 'add item' form.  It is recommended that you enable new user registration
on your wordpress site to enable user-generated content.  This plugin requires users to be registered and logged in to add content.</p>
<p>Each time a new item is added, a new post is also created.  This post won't show up in your regular blog, but rather, is fodder for search
engines.  The new posts that are created by this plugin are assigned to a custom post type of "where-can-i-find" which can be viewed via 
<a href="<?php echo get_site_url(); ?>/where-can-i-find"><?php echo get_site_url(); ?>/where-can-i-find</a>.  An RSS feed can also be accessed
for these posts (for automatic twitter updates, facebook notes etc).  This feed can be accessed through <a href="<?php echo get_bloginfo_rss('rss2_url') ?>?post_type=where-can-i-find"><?php echo get_bloginfo_rss('rss2_url') ?>?post_type=where-can-i-find</a>
</p>
<h3>Usage</h3>
<p>
Usage is simple:<br />
first: create a page (or post) with the shortcode [wcifadditem] - this will display a form that allows users<br />
to add items to your directory.  Users MUST be registered and logged in to add items.  If user registration is not allowed on your site
then a message will be displayed that only administrators who are logged in can add items.<br />
</p>
<p>second: create a page (or post) with the shortcode [wcifsearch]<br />
this will display a listing of items and where they can be found in your city.<br />
</p>
<p>*NOTE* the intention is for only one page/post with [wcifadditem] to exist.  Adding more than one page/post with the shortcode may create 
unpredictible results.</p>
<p>To see the plugin in action, visit <a href="http://www.whatupkaohsiung.com/the-where-can-i-find-stuff-in-kaohsiung-list/">What Up, Kaohsiung</a></p>
<p>For website development needs or for help with the plugin, contact the plugin author at <a href="http://www.vagabumming.com/hire-me-for-your-website-development-needs/">Vagabumming</a></p>
<p>Please keep in mind, this is free software that has no guarantee that it is suitable for any purpose</p>
<hr>
<div id="icon-options-general" class="icon32"></div><br /><h2>Options:</h2><p>&nbsp;</p>
<h3>Here you can set the city of the 'Where can I find' directory.</h3><br />
<p>The city is currently set to:<strong> <?php echo $wcif_city; ?></strong><br /></p>
<p>&nbsp;</p>
<form id="wcifcity" method="post" action="">
<input type="hidden" name="wcifupdatecity" value="Y" />
<input type="hidden" name="updatecitynonce" value="<?php echo wp_create_nonce('updatecitynonce'); ?>" />
<ul>
<li><label for="wcifcity">New Location:</label>
<input id="wcifcity" type="text" name="wcifcity" value="" /></li>
</ul>
<input class="button-primary" type="submit" value="update" />
</form>
<h3>Here you can set/change the categories</h3>
<p>
<strong>*NOTE*</strong>  This action WILL NOT change or alter category names of items that already exist in your list</p>
<p>These are the categories that are displayed on the 'add item' form</p>
<form id="wcifcategories" method="post" action="">
<ul>
<?php
$wcif_categories = get_option('wcif_categories');
	for($i = 0; $i< count($wcif_categories); $i++){
		echo "<li><input id=\"" . $wcif_categories[$i] . "\"type=\"checkbox\" name=\"categories[]\" value=\"" . $i . "\" />";
		echo "<label for=\"" . $wcif_categories[$i] . "\"> Category Name: " . $wcif_categories[$i] . "</label></li>\n";
		}
?>
</ul>
<input type="hidden" name="wcifupdateoptions" value="Y" />
<input type="hidden" name="updateoptionnonce" value="<?php echo wp_create_nonce('updateoptionnonce') ?>" />
<input class="button-primary" type="submit" value="delete selected categories" name="delete" />
</form>
<form id="wcifaddcategory" method="post" action="">
<input type="hidden" name="wcifaddcategory" value="Y" />
<input type="hidden" name="updatecategorynonce" value="<?php echo wp_create_nonce('updatecategorynonce') ?>" />
<ul>
<p>&nbsp;</p>
<li><label for="newcategory">Add New Category:</label><br />
<input id="newcategory" type="text" name="wcifcategory" />
</li>
</ul>
<br />
<input class="button-primary" type="submit" value="add category" name="add" />
</form>
</div>
<?php
}
function wcif_search(){
global $wcif_deps_loaded, $wpdb;
$wcif_deps_loaded = true;
$additemquery = "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_content LIKE '%wcifadditem%' AND post_status = 'publish' ";
$additemid = $wpdb->get_var($additemquery);
wp_print_scripts('tiptip');
wp_print_scripts('jpajinate');
?>
<form id="wcif_searchform" class="box" onsubmit="return false">Find an item in <?php echo get_option('wcif_city'); ?>
<p><input type="text" name="wcif_search" /></p>
<input id="wcifsearch" type="submit" value="Search">
</form>
<script type="text/javascript">
jQuery(document).ready(function(){
	jQuery(".tiptip").tipTip();
	jQuery("#wcifresults").pajinate({items_per_page : 10});
	jQuery("a[href='#']").click(function(event){event.preventDefault();});

jQuery('#wcif_searchform').submit(function(event){
		event.preventDefault;
		var wcif_search = jQuery("input[name='wcif_search']").attr('value');
		var wcifnonce = '<?php echo wp_create_nonce('wcifnonce') ?>';
		var postUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
		jQuery.post(postUrl,{action: "wcifsearch", wcif_search : wcif_search, wcifnonce : wcifnonce}, function(data){
			var msg = jQuery(data).find("#return");
			var count = jQuery(data).find("ul.content").children().length;
				if (count < 10){
					var newlist = jQuery(data).find("#wcifresults");
					}else{
					var newlist = jQuery(data).find("#wcifresults").pajinate({items_per_page : 10});
					}
			jQuery('#searchresults').empty().append(msg);
			jQuery("#wcifresults").empty().append(newlist);
			jQuery(".tiptip").tipTip();
			jQuery("input[name='wcif_search']").val('');
			});                        
		})
})

</script>		
<?php if(isset($additemid)){ ?>
	<center><a href=" <?php echo get_permalink( $additemid); ?>">add an item to the Where Can I Find stuff in  <?php echo get_option('wcif_city'); ?> list</a><br /><hr></center>
<?php }?>
		<div id="searchresults"></div>
		<div id="wcif_output">
		
		<div id="wcif_column1"><h3> Category </h3></div>
		<div id="wcif_column2"><h3> Item </h3></div>
		<div id="wcif_column3"><h3> Location </h3></div>
	
	
<?php
where_can_i_find::wcifsearchquery();
echo '</div>';
}
function wcifsearchquery() {
global $wcif_deps_loaded, $wpdb;
$additemquery = "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_content LIKE '%wcifadditem%' AND post_status = 'publish' ";
$additemid = $wpdb->get_var($additemquery);
$searchpostquery = "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_content LIKE '%wcifsearch%' AND post_status = 'publish' ";
$searchpostid = $wpdb->get_var($searchpostquery);
$table_name = $wpdb->prefix . "wcif";
if(isset($_POST['wcif_search'])){
	$wcif_search = $_POST['wcif_search'];//the user's search term
	}

//conditionally set the search query
	if(!isset($_POST['wcif_search']))
	{
		
	$result = $wpdb->get_results($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."wcif"));
	$numresults = count($result);
	$myrows = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wcif"));
	}
	
	else{
		check_ajax_referer('wcifnonce','wcifnonce');
		
	$myrows = $wpdb->get_results($wpdb->prepare("SELECT wcif_item FROM $table_name")); //this query loads the lev algorithm for spell checking
	foreach($myrows as $row){
		$lev = levenshtein($row->wcif_item, $wcif_search);	
			if ($lev < 3){ 
				$closematches[] = $row->wcif_item;
			}
		}
		$matches = array($wcif_search);
	if (is_array($closematches)) { $matchlist = array_merge($matches,$closematches); }
	else {$matchlist = $matches;
		}
	foreach($matchlist as $s){
	$matchwords[] = $s ;
	}
	foreach($matchwords as $s ){
	$searchstringitems[]= "(wcif_item LIKE '%" . $s . "%')";
	}
	$final_search_string = implode(' OR ',$searchstringitems);
	$myquery = "SELECT id FROM $table_name WHERE $final_search_string "; 
	$result = $wpdb->get_results($myquery);
	$numresults = count($result);
	$myquery = "SELECT * FROM $table_name WHERE $final_search_string "; 
	
	echo '<div id="returnedresults">';	
	
	if($numresults > 0){
		if($numresults == 1){$resultcount = "result";}
		else{ $resultcount = "results";}
		echo '<div id="return"><p>your search returned ' . $numresults . ' ' . $resultcount . '.  Return to the <a href='  . get_permalink() . '>where can I find stuff in ' . get_option('wcif_city') . ' list </a></p></div>';
		$myrows = $wpdb->get_results($myquery);
		}
		else{
		$result = $wpdb->get_results($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."wcif"));
		$numresults = count($result);
		$myrows = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wcif"));
		echo "<div id=\"return\">Your search didn't return any results. The complete list is below: <br /><br /></div>";
		}
	}

	echo "<div id=\"wcifresults\">";
	echo "<div class=\"page_navigation\" style=\"padding: 10px;\"></div>";
	echo "<ul class=\"content\">";
	foreach($myrows as $row){
	if($row->wcif_details != '') {
		$details_title = "<a class=\"tiptip\" href=\"#\" title=\"" . stripslashes($row->wcif_details) . "\">" . stripslashes($row->wcif_item) . "</a>";
		}
		else {$details_title = stripslashes($row->wcif_item);}
	if(!empty($row->wcif_locationdetails)){
		$location_details = '<a class="tiptip" href="' . $row->url . '" title="' . stripslashes($row->wcif_locationdetails) . '">' .stripslashes($row->wcif_location) . '</a>'; 
		}
	else{
		$location_details = stripslashes($row->wcif_location);
		}		
echo "<li><div id=\"wcif_output\"><div id=\"wcif_column1\">" . stripslashes($row->wcif_category) . "</div> <div id=\"wcif_column2\">" . $details_title . "</div><div id=\"wcif_column3\"> " . $location_details . "</div></div><br /><br /><hr></li>\n";
unset($wcif_details);
}
	echo "</ul><div class=\"page_navigation\" style=\"padding: 10px;\"></div></div>";
	if(isset($additemid)){
	echo '<div><br /><a href="' . get_permalink( $additemid) . '">add an item to the \'Where Can I Find stuff in ' . get_option('wcif_city') . ' list</a><br /></div>';
	}
}
function wcif_additem() {
global $wcif_deps_loaded;
$wcif_deps_loaded = true;
global $current_user;
global $wpdb;
get_currentuserinfo();
$wcif_table_name = $wpdb->prefix ."wcif";
$myquery = "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_content LIKE '%wcifsearch%' AND post_status = 'publish' ";
$searchpostid = $wpdb->get_var($myquery);
$myquery = "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_content LIKE '%wcifadditem%' AND post_status = 'publish' ";
$additemid = $wpdb->get_var($myquery);	
$post_cat = get_cat_ID('wcif');

//set our values
$wcif_getrawinput = array(wcif_item => $_POST['wcif_item'], wcif_details => $_POST['wcif_details'], wcif_category => $_POST['wcif_category'], wcif_contributor => $_POST['wcif_contributor'], wcif_url => $_POST['wcif_url'], wcif_locationdetails => $_POST['wcif_locationdetails'], wcif_location => $_POST['wcif_location']);
$wcif_getinput = str_replace(array("\r\n","\r")," ", $wcif_getrawinput);
if(get_option('users_can_register') == 0 && (!current_user_can('update_core'))){
	echo 'only administrators who are logged in can add items to the list';
	}else{
if(is_user_logged_in()) {

//user is logged in
		

		if(! empty($wcif_getinput['wcif_item']) && ! empty($wcif_getinput['wcif_location'])) {
		$nonce = $_POST['wcifaddnonce'];
		if(! wp_verify_nonce($nonce, 'wcifaddnonce')){
			echo "Something has gone terribly awry, perhaps your kitteh has jumped on your keyboard";
			die();
			}
		echo "Thanks for your submission " . $current_user->display_name . ".  Click here to browse the \"<a href=\" " . get_permalink($searchpostid) . "\">Where Can I Find stuff in " . get_option('wcif_city') . "</a>\" List";
		if (!(isset($_POST['my-form-data']))) { echo "Something went terribly awry.  Check for spilled beer on your keyboard<br />"; break;}
		
		
		if(filter_var($wcif_getinput['wcif_url'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)){
		}
		else{
		$wcif_getinput['wcif_url'] = '#';
		}
		
		$wpdb->query($wpdb->prepare("
				INSERT INTO ".$wcif_table_name."
				(id, time, wcif_item, wcif_location, wcif_category, wcif_details, contributor, url, wcif_locationdetails)
				VALUES ('NULL', %s, %s, %s, %s, %s, %s, %s, %s)",
				current_time('mysql'), $wcif_getinput['wcif_item'], $wcif_getinput['wcif_location'], $wcif_getinput['wcif_category'], $wcif_getinput['wcif_details'], $current_user->ID, $wcif_getinput['wcif_url'], $wcif_getinput['wcif_locationdetails']));
				
			$post_content = '<p>' . $current_user->display_name . ' has just added ' . $wcif_getinput['wcif_item'] . ' to the "<a href = "' . get_permalink($searchpostid) . '">Where can I find stuff in ' . get_option('wcif_city') . ' list.</a>" if you\'re looking for this item, you can probabaly find it at this location: ' . $wcif_getinput['wcif_location'] . '.</p>' ;
			if ($wcif_getinput['wcif_details'] !=''){ $post_content .= '<p>the detailed description for ' . $wcif_getinput['wcif_item'] . ' is: <blockquote>' . $wcif_getinput['wcif_details'] . '</blockquote></p><br />';}
			if ($wcif_getinput['wcif_locationdetails'] != ''){ $post_content .= '<p>' . $wcif_getinput['wcif_location'] . ' is described as: <blockquote>' . $wcif_getinput['wcif_locationdetails'] . '.</blockquote></p>';}
			if(filter_var($wcif_getinput['wcif_url'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)){ $post_content .= '<p>Click to visit <a href="' . $wcif_getinput['wcif_url'] . '">'. $wcif_getinput['wcif_location'] . '</a> on the web';}
			$wcif_post = array(
				'post_title' => $wcif_getinput['wcif_item'] . ' in ' . get_option('wcif_city'),
				'post_content' => $post_content,
				'post_status' => 'publish',
				'post_author' => $current_user->ID,
				'post_category' => array($post_cat),
				'tags_input' => 'wcif',
				'post_type' => 'where-can-i-find'
				);
				
			wp_insert_post($wcif_post);
		
		}
		else{ 

//no values are set yet, show the form
if($wcif_getinput['wcif_url'] == "" )
{ $wcif_getinput['wcif_url'] = "http://";}

	$form = '
					<form action="'. get_permalink($additemid) . '" method="post">
						<h3>Tell us where something is in ' . get_option('wcif_city') . '</h3> 
						<br /><label for="wcif_category">Select an appropriate category for your item:</label><br />
						<select name="wcif_category">';
						
						$myrows = get_option('wcif_categories');//$wpdb->get_results($wpdb->prepare("SELECT wcif_categories FROM " . $wpdb->prefix . "wcif_staticvalues ORDER BY FIELD(wcif_categories,'". $wcif_getinput['wcif_category'] . "') DESC"));
						foreach($myrows as $row){
						$form .=  '<option>' . stripslashes($row) . '</option>';
						}
					
						$form .= '</select> <label for="wcif_item"><br />
      						Give a brief description of the item:<br /></label>
  							<input type="text" name="wcif_item" id="wcif_item" maxlength="30" value="' . $wcif_getinput['wcif_item'] . '"  />* required<br />
  							
  							<label for="wcif_details">Give details of the item (optional)</label><br />
    						<textarea name="wcif_details" id="wcif_details" cols="45" rows="5" >' . $wcif_getinput['wcif_details'] . '</textarea><br />
    						
    						<label for="wcif_location">Briefly tell us where to find this item: </label><br />
    						<input type="text" name="wcif_location" id="wcif_location" maxlength="30" value="' . $wcif_getinput['wcif_location'] . '"/> *required<br />
    						
  							
    						<label for="wcif_locationdetails">Give details of where to find the item (optional)</label><br />
    						<textarea name="wcif_locationdetails" id="wcif_locationdetails" cols="45" rows="5" >' . $wcif_getinput['wcif_locationdetails'] . '</textarea><br />
  							
                        	
                        	
  							
    						<label for="wcif_url">Link e.g. google map link, website of store, etc. (optional)</label><br />
   						    <input type="text" name="wcif_url" id="wcif_url" value="' . $wcif_getinput['wcif_url'] . '" maxlength="255"/><br />
 							
 							
							
							<input type="hidden" name="my-form-data" value="process"/>
							<input type="hidden" name="notify" value="True"/>
							<input type="hidden" name="wcifaddnonce" value="' . wp_create_nonce('wcifaddnonce') . '"/> 
							<center><input type="submit" id="submit" name="Submit" value="Submit"/></center></form>
						    </p>';
				echo $form;
		}
}

else {
		echo "you must be registered and logged in to add items";
        echo "<br />";
        self::wcif_register_login();
	}
}
}
function wcif_register_login(){
global $wcif_deps_loaded;
$wcif_deps_loaded = true;
?>
<div id="login-register-password">

	<?php global $user_ID, $user_identity; get_currentuserinfo(); if (!$user_ID) { ?>

	<ul class="tabs_login">
		<li class="active_login"><a href="#tab1_login">Login</a></li>
		<li><a href="#tab2_login">Register</a></li>
		<li><a href="#tab3_login">Forgot?</a></li>
	</ul>
	<div class="tab_container_login">
		<div id="tab1_login" class="tab_content_login">

			<?php $register = $_GET['register']; $reset = $_GET['reset']; if ($register == true) { ?>

			<h3>Success!</h3>
			<p>Check your email for the password and then return to log in.</p>

			<?php } elseif ($reset == true) { ?>

			<h3>Success!</h3>
			<p>Check your email to reset your password.</p>

			<?php } else { ?>

			<h3>Have an account?</h3>
			<p>Log in or sign up! It&rsquo;s fast &amp; <em>free!</em></p>

			<?php } ?>

			<form class="wp-user-form">
				<div id="error"></div>
				<div class="username">
					<label for="user_login"><?php _e('Username'); ?>: </label>
					<input type="text" name="log" value="<?php echo esc_attr(stripslashes($user_login)); ?>" size="20" id="user_login" tabindex="11" />
				</div>
				<div class="password">
					<label for="user_pass"><?php _e('Password'); ?>: </label>
					<input type="password" name="pwd" value="" size="20" id="user_pass" tabindex="12" />
				</div>
				<div class="login_fields">
					<div class="rememberme">
						<label for="rememberme">
							<input type="checkbox" name="rememberme" value="forever" checked="checked" id="rememberme" tabindex="13" /> Remember me
						</label>
					</div>
				
					<input type="submit" name="user-submit" value="<?php _e('Login'); ?>" tabindex="14" class="user-submit" />
					<input type="hidden" name="redirect_to" value="<?php echo $_SERVER['REQUEST_URI']; ?>" />
					<input type="hidden" name="user-cookie" value="1" />
				</div>
			</form>
		</div>
		<div id="tab2_login" class="tab_content_login" style="display:none;">
			<h3>Register for this site!</h3>
			<p>Sign up now for the good stuff.</p>
			<div id="register_error"></div>
			<form method="post" action="<?php echo site_url('wp-login.php', 'login_post') ?>" class="wp-user-form">
				<div class="username">
					<label for="user_login"><?php _e('Username'); ?>: </label>
					<input type="text" name="user_login" value="<?php echo esc_attr(stripslashes($user_login)); ?>" size="20" id="user_login" tabindex="101" />
				</div>
				<div class="password">
					<label for="user_email"><?php _e('Your Email'); ?>: </label>
					<input type="text" name="user_email" value="<?php echo esc_attr(stripslashes($user_email)); ?>" size="25" id="user_email" tabindex="102" />
				</div>
				<div class="login_fields">
					<?php do_action('register_form'); ?>
					<input type="submit" name="user-submit" value="<?php _e('Sign up!'); ?>" class="user-submit" tabindex="103" />
					<?php $register = $_GET['register']; if($register == true) { echo '<p>Check your email for the password!</p>'; } ?>
					<input type="hidden" name="redirect_to" value="<?php echo $_SERVER['REQUEST_URI']; ?>?register=true" />
					<input type="hidden" name="user-cookie" value="1" />
				</div>
			</form>
		</div>
		<div id="tab3_login" class="tab_content_login" style="display:none;">
			<h3>Lose something?</h3>
			<p>Enter your username or email to reset your password.</p>
			<form method="post" id="reset" action="<?php echo site_url('wp-login.php', 'login_post') ?>" class="wp-user-form">
				<div id="reset_error"></div>
				<div class="username">
					<label for="user_login" class="hide"><?php _e('Username or Email'); ?>: </label>
					<input type="text" name="user_login" value="" size="20" id="user_login_lost_password" tabindex="1001" />
				</div>
				<div class="login_fields">
					<?php do_action('login_form', 'resetpass'); ?>
					<input type="submit" name="user-submit" value="<?php _e('Reset my password'); ?>" class="user-submit" tabindex="1002" />
					<?php $reset = $_GET['reset']; if($reset == true) { echo '<p>A message will be sent to your email address.</p>'; } ?>
					<input type="hidden" name="redirect_to" value="<?php echo $_SERVER['REQUEST_URI']; ?>?reset=true" />
					<input type="hidden" name="user-cookie" value="1" />
				</div>
			</form>
		</div>
	</div>

	<?php }?>

</div>
<?php 
}
function ajax_my_registration(){
	
   $login = wp_signon();
   $response = array(
   'what'=> 'user_log_in',
   'action'=> 'is_user_logged_in?',
   'id'=> '1'
	);
	
if(is_wp_error($login)){
	$response['data'] = 'tehre was errir';
	}
	else{
	global $current_user;
	$user_data = get_userdatabylogin($_POST['log']);
	$response['data'] = $user_data->display_name . ' is logged in';
	}
	$xmlResponse = new WP_Ajax_Response($response);
	$xmlResponse->send();

}
}
$wherecanifindplugin = new where_can_i_find;
?>
