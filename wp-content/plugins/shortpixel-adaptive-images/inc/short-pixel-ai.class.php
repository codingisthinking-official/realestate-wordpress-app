<?php
/**
 * User: simon
 * Date: 16.06.2018
 */

class ShortPixelAI
{

    const DEFAULT_API_AI = 'https://cdn.shortpixel.ai';
    const DEFAULT_API_AI_PATH = '/spai';
    const SP_API = 'https://api.shortpixel.com/';
    const SEP = '+'; //can be + or ,
    const LOG_NAME = 'shortpixel_ai_log';
    public static $SHOW_STOPPERS = array('ao', 'avadalazy', 'ginger');

    public $settings;
    public $cssCacheVer;

    public $lazyNoticeThrown = false;
    public $affectedTags = array();

    /**
     * @var $instance
     */
    private $file;
    private static $instance;
    private $debug = false;
    private $doingAjax = false;

    private $conflict = false;
    private $spaiJSDequeued = false;

    private $integrations = false;
    private $logger = false;
    private $parser = false;
    private $cssParser = false;

    /**
     * @return ShortPixelRegexParser
     */
    public function getRegexParser() {
        return $this->parser;
    }

    /**
     * @return bool|ShortPixelCssParser
     */
    public function getCssParser()
    {
        return $this->cssParser;
    }

    public function doingAjax() {
        return $this->doingAjax;
    }

    /**
     * Make sure only one instance is running.
     */
    public static function instance($pluginMain)
    {
        if (!isset (self::$instance)) {
            self::$instance = new self($pluginMain);
        }
        return self::$instance;
    }

    private function __construct($pluginFile)
    {
        load_plugin_textdomain('shortpixel-adaptive-images', false, plugin_basename(dirname(SHORTPIXEL_AI_PLUGIN_FILE)) . '/lang');

        $this->logger = ShortPixelAILogger::instance();
        //$parser = new ShortPixelRegexParser($this);
        //$parser = new ShortPixelDomParser($this);
        $this->cssParser = new ShortPixelCssParser($this);
        //$this->parser = new ShortPixelSimpleDomParser($this);
        $this->parser = new ShortPixelRegexParser($this);

        //The recorded affected tags are from pieces of content that are loaded after the page, for example AJAX content. The first time the image will be blank but at second load OK
        $this->affectedTags = $this->getRecordedAffectedTags();

        $this->doingAjax = (function_exists("wp_doing_ajax") && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX);
        $this->setup_globals($pluginFile);
        $this->include_libs();
        $this->setup_hooks();
        add_action('admin_notices', array(&$this, 'display_admin_notices'));
    }

    public function init_ob()
    {
        if ($this->isWelcome()) {
            $this->logger->log("WILL PARSE " . $_SERVER['REQUEST_URI']);
            //remove srcset and sizes param
            add_filter('wp_calculate_image_srcset', array($this, 'replace_image_srcset'), 10, 5);

            ob_start(array($this, 'maybe_replace_images_src'));
        } elseif(defined('SHORTPIXEL_AI_CLEANUP')) {
            $this->logger->log("CLEANUP " . $_SERVER['REQUEST_URI']);
            ob_start(array($this, 'maybe_cleanup'));
        } else {
            $this->logger->log("WON'T PARSE " . $_SERVER['REQUEST_URI']);
        }
    }

    private function include_libs()
    {
        require_once($this->plugin_dir . 'inc/settings_page.php');
    }

    private function setup_globals($pluginFile)
    {
        $this->file = $pluginFile;
        $this->basename = plugin_basename($this->file);
        $this->plugin_dir = plugin_dir_path($this->file);
        $this->plugin_url = plugin_dir_url($this->file);
        $gravatar = 'regex:/\/\/([^\/]*\.|)gravatar.com\//';

        if(get_option('spai_settings_api_url') ===  false) {
            //need to setup the defaults
            add_option('spai_settings_api_url', ShortPixelAI::DEFAULT_API_AI . self::DEFAULT_API_AI_PATH);
            add_option('spai_settings_compress_level', 1);
            add_option('spai_settings_type', 1);
            add_option('spai_settings_fadein', 1);
            add_option('spai_settings_webp', 1);
            add_option('spai_settings_excluded_paths', $gravatar);
        }
        if(get_option('spai_settings_backgrounds_lazy') === false) { //migrate from 1.2.6 to 1.3.0
            add_option('spai_settings_backgrounds_lazy', 0);
            update_option('spai_settings_remove_exif', 1);
        }
        if(get_option('spai_settings_excluded_paths') === false) {
            add_option('spai_settings_excluded_paths', $gravatar);
        }
        if(get_option('spai_settings_eager_selectors') === false && get_option('spai_settings_noresize_selectors') !== false) {
            //for backwards compatibility, the eager should take the values from noresize because noresize was also eager.
            add_option('spai_settings_eager_selectors', get_option('spai_settings_noresize_selectors'));
        }
        $this->settings = array(
            'api_url' => get_option('spai_settings_api_url'),
            'compress_level' => get_option('spai_settings_compress_level'),
            'remove_exif' => get_option('spai_settings_remove_exif', 1),
            'excluded_selectors' => get_option('spai_settings_excluded_selectors', ''),
            'eager_selectors' => get_option('spai_settings_eager_selectors', ''),
            'noresize_selectors' => get_option('spai_settings_noresize_selectors', ''),
            'excluded_paths' => get_option('spai_settings_excluded_paths'),
            'type' => get_option('spai_settings_type', 1),
            'crop' => get_option('spai_settings_crop', 0),
            'fadein' => get_option('spai_settings_fadein', 1),
            'webp' => get_option('spai_settings_webp', 1),
            'backgrounds_lazy' => get_option('spai_settings_backgrounds_lazy'),
            'backgrounds_max_width' => get_option('spai_settings_backgrounds_max_width'),
            'parse_json' => get_option('spai_settings_parse_json'),
            'parse_json_lazy' => get_option('spai_settings_parse_json_lazy'),
            'parse_css_files' => get_option('spai_settings_parse_css_files'),
            'css_domains' => get_option('spai_settings_css_domains'),
            'ext_meta' => get_option('spai_settings_ext_meta')
        );

        if($this->settings['parse_css_files']) {
            $this->cssCacheVer = get_option('spai_settings_css_ver', 1);
        }

        if(SHORTPIXEL_AI_DEBUG) {
            foreach($this->settings as $key => $value) {
                if(isset($_GET[$key])) {
                    $this->settings[$key] = $_GET[$key];
                }
            }
        }
    }

    private function setup_hooks()
    {
        //if (!is_admin()) {
        //    add_action( 'admin_bar_menu', array( &$this, 'toolbar_sniper_bar'), 999 );
        //    add_action( 'wp_enqueue_scripts', array( &$this, 'toolbar_sniper_scripts') );
        //}

        //if(!(is_admin() && !wp_doing_ajax() /* && function_exists("is_user_logged_in") && is_user_logged_in() */)) {
        if (!is_admin() || $this->doingAjax) {
            //FRONT-END
            if (!in_array($this->is_conflict(), self::$SHOW_STOPPERS)) {
                //setup to replace URLs only if not admin.
                add_action('wp_enqueue_scripts', array(&$this, 'enqueue_script'), 11);
                add_action('init', array(&$this, 'init_ob'), 1);
                // USING ob_ instead of the filters below.
                //add_filter( 'the_content', array( $this, 'maybe_replace_images_src',));
                //add_filter( 'post_thumbnail_html', array( $this, 'maybe_replace_images_src',));
                //add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'maybe_replace_images_src',));

                $integrations = $this->getActiveIntegrations();
                if($integrations['wp-rocket']['minify-css'] && $integrations['wp-rocket']['css-filter']) {
                    $this->logger->log('SETUP WP ROCKET FILTER');
                    // if WP Rocket is active and the css option is on and the version is >=3.4 we can use its cache to store the changed CSS
                    add_filter('rocket_css_content', array(&$this, 'parse_cached_css'), 10, 3);
                }
                //Disable the Cloudflare Rocket Loader for ai.min.js
                add_filter( 'script_loader_tag', array(&$this, 'disable_rocket_loader'), 10, 3 );
            }

            //EXCEPT our AJAX actions which are front but also from admin :)
            if (is_admin()) {
                add_action('wp_ajax_shortpixel_ai_dismiss_notice', array(&$this, 'dismiss_admin_notice'));
                add_action('wp_ajax_shortpixel_ai_clear_css_cache', array(&$this, 'clear_css_cache'));
                add_action('wp_ajax_shortpixel_ai_add_selector_to_list', array(&$this, 'add_selector_to_list'));
                add_action('wp_ajax_shortpixel_ai_remove_selector_from_list', array(&$this, 'remove_selector_from_list'));
            }
        } else {
            //BACK-END
            add_action('admin_head', array(&$this, 'enqueue_admin_styles'), 1);
            add_action('admin_footer', array(&$this, 'enqueue_admin_script'), 11);

            add_filter('plugin_action_links_' . plugin_basename($this->file), array(&$this, 'generate_plugin_links'));//for plugin settings page
        }
    }

    public function admin_enqueue_scripts()
    {
    }

    public function toolbar_sniper_bar($wp_admin_bar) {
        if(!is_user_logged_in() || !current_user_can( 'edit_others_posts')) return;
        $args = array(
            'id'    => 'shortpixel_ai_sniper',
            'title' => '<div id="shortpixel_ai_sniper" title="' . __('Select an image to refresh on CDN or exclude','shortpixel-adaptive-images') . '" class="shortpixel_ai_sniper"><img src="'
                . plugins_url( 'img/spai-sniper.png', SHORTPIXEL_AI_PLUGIN_FILE ) . '" success-url="javascript:spaiSnip();" style="margin-top: 4px;" class="spai-snip-menu-popup-trigger shortpixel_ai_sniper">'
                    . '<div id="spai-snip-menu-popups">
                            <div id="spai-snip-menu-popup-multiple" class="spai-snip-menu-popup">
                                <p id="spai-snip-menu-popup-multiple-title">' . __('Please choose an image from the following list.','shortpixel-adaptive-images') . '</p>
                                <div id="spai-snip-menu-popup-multiple-list"></div>
                            </div>
                            <div id="spai-snip-menu-popup-single-template" class="spai-snip-menu-popup">
                                <div class="spai-snip-menu-popup-single-item-container">
                                    <div class="spai-snip-menu-popup-single-item-container-image-container">
                                        <img src="#" class="spai-snip-menu-popup-single-item-container-image" alt="">
                                    </div>
                                    <span class="spai-snip-menu-popup-single-item-container-basename"></span>
                                </div>
                                <p class="spai-snip-menu-popup-single-title"></p>
                                <div class="spai-snip-menu-popup-single-options"></div>
                            </div>
                       </div>
					   <div id="spai-snip-loader" class="spai-snip-loader">
							<img src="' . plugins_url( 'img/Spinner-1s-200px.gif', SHORTPIXEL_AI_PLUGIN_FILE ) . '" alt="" class="spai-snip-loader-img" />
							<p class="spai-snip-loader-text">' . __('Loading...','shortpixel-adaptive-images') . '</p>
                       </div>'
                .'</div>',
            'href'  => '#',
            'meta'  => array('class' => 'shortpixel-ai-sniper')
        );
        $wp_admin_bar->add_node( $args );
    }

    public function toolbar_sniper_scripts() {
        if(!is_user_logged_in() || !current_user_can( 'edit_others_posts')) return;
        wp_enqueue_style('spai-bar-style', $this->plugin_url . 'css/style-bar.css', array(), SHORTPIXEL_AI_VERSION);
        wp_enqueue_script('spai-snip', $this->plugin_url . 'js/snip.js', array('jquery'), SHORTPIXEL_AI_VERSION, true);
    }
        public function generate_plugin_links($links)
    {
        $in = '<a href="options-general.php?page=shortpixel_ai_settings_page">Settings</a>';
        array_unshift($links, $in);
        return $links;
    }

    function disable_rocket_loader( $tag, $handle, $src ) {
        if ( 'spai-scripts' === $handle ) {
            //$tag = str_replace( 'src=', 'data-cfasync="false" src=', $tag );
            $tag = str_replace( '<script', '<script data-cfasync="false"', $tag );
        }
        return $tag;
    }

    function parse_cached_css($content, $source, $target) {
        $this->logger->log("PARSE WP-ROCKET CSS from $source into $target");
        $this->cssParser->cssFilePath = $target;
        return $this->cssParser->replace_inline_style_backgrounds($content);
        $this->cssParser->cssFilePath = false;
    }

    public function enqueue_script()
    {
        if (!$this->isWelcome()) {
            return;
        }
        if ($this->integrations === false) {
            $this->integrations = $this->getActiveIntegrations();
        }

        if ($this->settings['fadein']) {
            wp_register_style('spai-fadein', false);
            wp_enqueue_style('spai-fadein');
            //Exclude the .zoomImg's as it conflicts with rules of WooCommerce.
            wp_add_inline_style('spai-fadein',
            'img[data-spai]:not(div.woocommerce-product-gallery img) {'
                    . 'opacity: 0;'
                . '} '
                . 'img[data-spai-eager]:not(div.woocommerce-product-gallery img),'
                . 'img[data-spai-upd]:not(div.woocommerce-product-gallery img) {'
                    . '-webkit-transition: opacity .5s linear 0.2s;'
                    . '-moz-transition: opacity .5s linear 0.2s;'
                    . 'transition: opacity .5s linear 0.2s; opacity: 1; '
                . '}');
        }

        wp_register_script('spai-scripts', $this->plugin_url . 'js/ai.min.js', array('jquery'), SHORTPIXEL_AI_VERSION, true);
        wp_localize_script('spai-scripts', 'spai_settings', array(
            'api_url' => $this->get_api_url(),
            'method' => $this->settings['type'] == 1 ? 'src' : ($this->settings['type'] == 0 ? 'srcset' : 'both'),
            'crop' => $this->settings['crop'],
            'debug' => $this->debug,
            'site_url' => home_url(),
            'plugin_url' => plugins_url('/shortpixel-adaptive-images'),
            'version' => SHORTPIXEL_AI_VERSION,
            'excluded_selectors' => $this->splitSelectors($this->settings['excluded_selectors']),
            'eager_selectors' => $this->splitSelectors($this->settings['eager_selectors']),
            'noresize_selectors' => $this->splitSelectors($this->settings['noresize_selectors']),
            'excluded_paths' => $this->splitSelectors($this->settings['excluded_paths']),
            'active_integrations' => $this->integrations,
            'parse_css_files' => $this->settings['parse_css_files'],
            'backgrounds_max_width' => $this->settings['backgrounds_max_width'],
            'sep' => self::SEP, //separator
            'webp' => $this->settings['webp'],
            'sniper' => $this->plugin_url . 'img/target.cur',
            'affected_tags' => '{{SPAI-AFFECTED-TAGS}}',
            'ajax_url' => admin_url('admin-ajax.php')
        ));
        wp_enqueue_script('spai-scripts');
    }

    public function splitSelectors($selectors) {
        $selArray = strlen($selectors) ? explode(',', str_replace("\n", ",", $selectors)) : array();
        return array_map('trim', $selArray);
    }

    public function enqueue_admin_styles()
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && ($screen->id == 'settings_page_shortpixel_ai_settings_page')) {
                wp_enqueue_style('spai-admin-style', $this->plugin_url . 'css/style.css');
                wp_enqueue_script('spai-quriobot', 'https://quriobot.com/qb/widget/KoPqxmzqzjbg5eNl/5doqer3ZpnmR6ZL0', null, false, true);
            }
        }
    }

    public function enqueue_admin_script()
    {
        wp_register_script('spai-admin-scripts', $this->plugin_url . 'js/admin.js', array('jquery'), SHORTPIXEL_AI_VERSION);
        wp_enqueue_script('spai-admin-scripts');
    }

    public function clear_css_cache() {
        update_option('spai_settings_css_ver', get_option('spai_settings_css_ver', 1) + 1);
    }

    public function add_selector_to_list() {
        $result = array('status' => 'error', 'message' => 'An error occurred, please contact support.');
        if(is_admin()) {
            if(empty($_POST['selector']) || !is_string($_POST['selector'])) {
                $result['message'] = 'Invalid selector has been provided.';
            }
            else if(empty($_POST['which_list']) || !is_string($_POST['which_list']) || !in_array($_POST['which_list'], array('noresize_selectors', 'excluded_selectors', 'eager_selectors'))) {
                $result['message'] = 'Invalid list has been provided.';
            }
            else {
                $selector = $_POST['selector'];
                $wp_option_name = 'spai_settings_' . $_POST['which_list'];
                $selectors_now = get_option($wp_option_name, '');
                $list = $this->splitSelectors($selectors_now);
                $result['status'] = 'ok';
                if(in_array($selector, $list)) {
                    $result['message'] = 'Selector already exists in the list.';
                }
                else {
                    $list[] = $selector;
                    if(update_option($wp_option_name, implode(',', $list))) {
                        $result['message'] = 'Selector has been added to the list.';
                    }
                    else {
                        $result['status'] = 'error';
                        $result['message'] = 'An error occurred, please contact support.';
                    }
                }
                $result['list'] = $this->splitSelectors(get_option($wp_option_name));
            }
        }
        else {
            $result['message'] = 'Please log in as admin.';
        }
        echo json_encode($result);
        wp_die();
    }

    public function remove_selector_from_list() {
        $result = array('status' => 'error', 'message' => 'An error occurred, please contact support.');
        if(is_admin()) {
            if(empty($_POST['selector']) || !is_string($_POST['selector'])) {
                $result['message'] = 'Invalid selector has been provided.';
            }
            else if(empty($_POST['which_list']) || !is_string($_POST['which_list']) || !in_array($_POST['which_list'], array('noresize_selectors', 'excluded_selectors', 'eager_selectors'))) {
                $result['message'] = 'Invalid list has been provided.';
            }
            else {
                $selector = $_POST['selector'];
                $wp_option_name = 'spai_settings_' . $_POST['which_list'];
                $selectors_now = get_option($wp_option_name, '');
                $list = $this->splitSelectors($selectors_now);
                $result['status'] = 'ok';
                if(!in_array($selector, $list)) {
                    $result['message'] = 'Selector does not exist in the list.';
                }
                else {
                    $list_new = array();
                    foreach($list as $list_element) {
                        if($list_element != $selector) {
                            $list_new[] = $selector;
                        }
                    }
                    if(update_option($wp_option_name, implode(',', $list_new))) {
                        $result['message'] = 'Selector has been removed from the list.';
                        $result = $this->clear_cache($result['message']);
                    }
                    else {
                        $result['status'] = 'error';
                        $result['message'] = 'An error occurred, please contact support.';
                    }
                }
                $result['list'] = $this->splitSelectors(get_option($wp_option_name));
            }
        }
        else {
            $result['message'] = 'Please log in as admin.';
        }
        echo json_encode($result);
        wp_die();
    }

    function clear_cache($message) {
        $result = array('status' => 'ok', 'message' => $message);
        $cache_cleared = false;
        wp_cache_flush();
        try {
            comet_cache::clear();
            $cache_cleared = true;
        } catch(Throwable $t) {} catch(Exception $e) {}
        if(function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $cache_cleared = true;
        }
        if(function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $cache_cleared = true;
        }
        if($cache_cleared) {
            $result['message'] .= ' Please refresh the page.';
        }
        else {
            $result['message'] .= ' Please clear the cache of your server and try again.';
        }
        return $result;
    }

    public static function activate()
    {
        /*
        Setup default options
         */
    }

    public static function deactivate()
    {
    }

    public function display_admin_notices()
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }
        //Compatibility notices

        //Autoptimize/Elementor External CSS/Avada Lazy Load image optimization notices, some not dismissable
        $conflict = $this->is_conflict();
        $dismissed = get_option('spai_settings_dismissed_notices', array());

        if ($conflict == 'ao') {
            $this->display_notice('ao', array('always-on' => true), '-scared');
        } elseif ($conflict == 'avadalazy') {
            $this->display_notice('avadalazy', array('always-on' => true), '-scared');
        } elseif ($conflict == 'divitoolbox') {
            $this->display_notice('divitoolbox', false, '-scared');
        } elseif ($conflict == 'elementorexternal' && !isset($dismissed['elementorexternal'])) {
            $this->display_notice('elementorexternal', false, '-scared');
        }

        if (false && !isset($dismissed['beta'])) {
            $this->display_notice('beta');
        }

        if (!isset($dismissed['twicelossy']) && $this->settings['compress_level'] == 1
            && is_plugin_active('shortpixel-image-optimiser/wp-shortpixel.php') && get_option('wp-short-pixel-compression', false)) {
            $this->display_notice('twicelossy');
        }

        $activeIntegrations = $this->getActiveIntegrations();
        //WPRocket?
        if (!isset($dismissed['lazy']) && $activeIntegrations['wp-rocket']['lazyload']) {
            $extra = array('msg' => '<a href="options-general.php?page=wprocket#media">' . __('Open the WP Rocket Settings', 'shortpixel-adaptive-images') . '</a>'
                . '&nbsp;' . __('to turn off the Lazy Load option.', 'shortpixel-adaptive-images'));
            $this->display_notice('lazy', $extra, '-scared');
        } elseif ($this->settings['parse_css_files'] && $activeIntegrations['wp-rocket']['minify-css'] && !$activeIntegrations['wp-rocket']['css-filter']){
            $this->display_notice('wprocketcss', false, '-scared');
        }
        //handle the thrown notices
        if (!isset($dismissed['lazy'])) {
            $thrown = get_transient("shortpixelai_thrown_notice");
            if (is_array($thrown)) {
                if ($thrown['when'] == 'lazy') {
                    $this->display_notice($thrown['when'], $thrown['extra'], '-scared');
                }
            }
        }
        if (!isset($dismissed['key'])) {
            $account = $this->get_shortpixel_account();
            if ($account->key) {
                $this->display_notice('key', array(
                    'email' => $account->email,
                    'button' => array('name' => 'Use this account', 'action' => 'useSPAccount')
                ));
            }
        }
        if (!isset($account->key) && !isset($dismissed['credits'])) {
            $domainStatus = $this->get_domain_status();
            if ($domainStatus->Status !== 2) {
                $this->display_notice('credits', array(
                    'status' => $domainStatus->Status,
                    'hasAccount' => $domainStatus->HasAccount,
                    'button' => array(
                        'name' => 'Check credits',
                        'action' => 'checkCredits',
                        'successmsg' => 'Yay! Your new credits are active. :-)',
                        'errormsg' => 'Still no credits :-(. Please check your account.')
                ));
            }
        }
    }

    public function display_notice($type, $data = false, $iconSuffix = '')
    {
        require_once($this->plugin_dir . 'inc/notices.php');
        new ShortPixelAINotice($type, $data, $iconSuffix);
    }

    public function is_conflict()
    {
        if (in_array($this->conflict, self::$SHOW_STOPPERS)) { // the elementorexternal doesn't deactivate the plugin
            return $this->conflict;
        }
        $this->conflict = 'none';
        if (!function_exists('is_plugin_active') || is_plugin_active('autoptimize/autoptimize.php')) {
            $autoptimizeImgopt = get_option('autoptimize_imgopt_settings', false); //this is set by Autoptimize version >= 2.5.0
            if ($autoptimizeImgopt) {
                $this->conflict = (isset($autoptimizeIsmgopt['autoptimize_imgopt_checkbox_field_1']) && $autoptimizeImgopt['autoptimize_imgopt_checkbox_field_1'] == '1' ? 'ao' : 'none');
            } else {
                $autoptimizeExtra = get_option('autoptimize_extra_settings', false); //this is set by Autoptimize version <= 2.4.4
                $this->conflict = (isset($autoptimizeExtra['autoptimize_extra_checkbox_field_5']) && $autoptimizeExtra['autoptimize_extra_checkbox_field_5']) ? 'ao' : 'none';
            }
        }

        if (function_exists('is_plugin_active') && is_plugin_active('divi-toolbox/divi-toolbox.php')) {
            $path = dirname(dirname(__DIR__)) . '/divi-toolbox/divi-toolbox.php';
            $pluginInfo = get_plugin_data($path);
            if(is_array($pluginInfo) && version_compare($pluginInfo['Version'], '1.4.2') < 0) {//older versions than 1.4.2 produce the conflict
                $diviToolboxOptions = unserialize(get_option('dtb_toolbox', '{}'));
                if(is_array($diviToolboxOptions) && isset($diviToolboxOptions['dtb_post_meta'])) {
                    $this->conflict = 'divitoolbox';
                    return $this->conflict;
                }
            }
        }
        if (function_exists('is_plugin_active') && is_plugin_active('lazy-load-optimizer/lazy-load-optimizer.php')) {
            $this->conflict = 'llopt';
            return $this->conflict;
        }
        if (function_exists('is_plugin_active') && is_plugin_active('ginger/ginger-eu-cookie-law.php')) {
            $ginger = get_option('ginger_general', array());
            if(isset($ginger['ginger_opt']) && $ginger['ginger_opt'] === 'in') {
                $this->conflict = 'ginger';
                return $this->conflict;
            }
        }

        $theme = wp_get_theme();
        if (strpos($theme->Name, 'Avada') !== false) {
            $avadaOptions = get_option('fusion_options', array());
            if (isset($avadaOptions['lazy_load']) && $avadaOptions['lazy_load'] == '1') {
                $this->conflict = 'avadalazy';
            }
        }

        if (!function_exists('is_plugin_active') || is_plugin_active('elementor/elementor.php') || is_plugin_active('elementor-pro/elementor-pro.php')) {
            $elementorCSS = get_option('elementor_css_print_method', false);
            if ($elementorCSS == 'external') {
                if(get_option('spai_settings_parse_css_files') === false) {
                    add_option('spai_settings_parse_css_files', 1);
                }
                if(!get_option('spai_settings_parse_css_files')) { //the option is explicitely unset by user
                    $this->conflict = 'elementorexternal';
                    return $this->conflict;
                }
            }
        }

        return $this->conflict;
    }

    public function get_shortpixel_account()
    {
        $email = array();
        $resp = new stdClass();
        if (($spKey = get_option('wp-short-pixel-apiKey', false)) && !get_option('spai_settings_account', false)) {
            $resp = $this->get_domain_status();
            if ($resp->Status == -3) {
                return $resp;
            }
            if ($resp->HasAccount) {
                return (object)array('status' => 3, 'Message' => 'already associated', 'key' => '', 'email' => '');
            }
            //the domain is not associated, check with SP API the user info for the key found locally
            $responseSP = wp_remote_get(self::SP_API . '/v2/user-info.php?key=' . $spKey, array('timeout' => 120, 'httpversion' => '1.1'));
            if (is_object($responseSP) && get_class($responseSP) == 'WP_Error') {
                return (object)array('status' => -3, 'Message' => 'connection error', 'key' => '', 'email' => '');
            }
            if (isset($responseSP['response'])) {
                $respSP = json_decode($responseSP['body']);
                $email = explode('@', $respSP->email);
                if (/* $resp->HasAccount && */
                    count($email) == 2) {
                    $email[0] = substr($email[0], 0, max(3, strlen($email[0]) / 2)) . "...";
                }
            }
        }
        return (object)array_merge((array)$resp, array('key' => $spKey, 'email' => implode('@', $email)));
    }

    public function get_domain_status($refresh = false)
    {
        if (!$refresh && ($domainStatus = get_transient('spai_domain_status'))) {
            $domainStatus->cached = 'yes';
            return $domainStatus;
        }
        //possible statuses: 2 OK (credits available, this is also for not associated domains) 0 - credits near limit,
        // -1 credits depleted, CDN active, -2 credits depleted, CDN inactive, -3 connection error
        $url = self::DEFAULT_API_AI . '/read-domain/' . parse_url(get_site_url(), PHP_URL_HOST);
        $response = wp_remote_get($url, array('timeout' => 120, 'httpversion' => '1.1'));
        if (is_object($response) && get_class($response) == 'WP_Error' || !isset($response['response'])) {
            return (object)array('Status' => -3, 'Message' => 'connection error: ' . $response->get_error_message());
        }
        $domainStatus = json_decode($response['body']);
        set_transient('spai_domain_status', $domainStatus, 600);
        return $domainStatus;
    }

    public function dismiss_admin_notice()
    {
        $noticeId = preg_replace('|[^a-z0-9]|i', '', $_GET['notice_id']);
        $action = preg_replace('|[^a-z0-9]|i', '', $_GET['call']);
        $response = false;
        switch ($action) {
            case 'useSPAccount':
                $success = $this->use_shortpixel_account();
                break;
            case 'checkCredits':
                $response = $this->get_domain_status(true);
                $success = ($response->Status == 2);
                break;
            default:
                $success = true;
        }
        if ($success) {
            $dismissed = get_option('spai_settings_dismissed_notices', array());
            $dismissed[$noticeId] = time();
            update_option('spai_settings_dismissed_notices', $dismissed);
        }
        die(json_encode(array("Status" => ($success ? 'success' : 'fail'), "Message" => 'Notice ID: "' . $noticeId . '"" dismissed', 'Details' => $response)));
    }

    public function use_shortpixel_account()
    {
        if (($spKey = get_option('wp-short-pixel-apiKey', false)) && !get_option('spai_settings_account', false)) {
            $response = wp_remote_get(self::DEFAULT_API_AI . '/add-domain/' . parse_url(get_site_url(), PHP_URL_HOST) . '/' . $spKey, array('timeout' => 120, 'httpversion' => '1.1'));
            if (isset($response['response'])) {
                $data = json_decode($response['body']);
                if ($data->Status == 2 || $data->Status == 1) {
                    update_option('spai_settings_account', true);
                    return true;
                }
            }
        }
        return false;
    }


    public function get_api_url($width = '%WIDTH%', $height = '%HEIGHT%')
    {
        if ($this->debug) {
            $args = array(
                array('shortpixel-ai' => 1)
            );
        } else {
            $args = array();
            //ATTENTION, w_ should ALWAYS be the first parameter if present! (see fancyboxUpdateWidth in JS)
            if ($width !== false) {
                $args[] = array('w' => $width);
                //$args[] = array('h' => $height);
            }
            $args[] = array('q' => ($this->settings['compress_level'] == 1 ? 'lossy' : ($this->settings['compress_level'] == 2 ? 'glossy' : 'lossless')));
            /*
            $args[] = ['g' => 'face'];
            $args[] = ['r' => 'max']
            $args[] = ['f' => 'auto']
            $args[] = ['x' => $this->settings['remove_exif'];*/
            $args[] = array('ret' => 'img');// img returns the original if not found, wait will wait for a quick resize
            //most proably not a good idea because of the page cache:
            /*if($this->browser_can_webp()) {
                $args[] = array('to' => 'webp');
            }*/
            if($this->settings['remove_exif'] == 0) {
                $args[] = array('ex' => '1');
            }
        }
        $api_url = $this->settings['api_url'];
        if (!$api_url) {
            $api_url = self::DEFAULT_API_AI . self::DEFAULT_API_AI_PATH;
        }
        $api_url = trailingslashit($api_url);
        /*
        Make args to be in desired format
         */
        foreach ($args as $arg) {
            foreach ($arg as $k => $v) {
                $api_url .= $k . '_' . $v . self::SEP;
            }
        }
        $api_url = rtrim($api_url, self::SEP);
        //$api_url = trailingslashit( $api_url );
        return $api_url;
    }

    protected function browser_can_webp() {
        $userAgent = explode(' ', $_SERVER['HTTP_USER_AGENT']);
        foreach($userAgent as $uaPart) {
            $uaPart = explode('/', $uaPart);
            if(count($uaPart) >= 2) {
                $ver = explode('.', $uaPart[1]);
                switch($uaPart[0]) {
                    case 'Chrome':
                        return intval($ver[0]) >= 32;
                    case 'Firefox':
                        return intval($ver[0]) >= 65;
                }
            }
        }
        return false;
    }

    public function maybe_replace_images_src($content)
    {

        if (!$this->doingAjax && !wp_script_is('spai-scripts')) {
            //the script was dequeued
            $this->logger->log("SPAI JS DEQUEUED ... and it's not AJAX");
            $this->spaiJSDequeued = true;
        }
        /*if(strpos($_SERVER['REQUEST_URI'],'action=alm_query_posts') > 0) {
            $this->logger->log("CONTENT: " . substr($content, 0, 200));
        //}*/
        if ((function_exists('is_amp_endpoint') && is_amp_endpoint())) {
            $this->logger->log("IS AMP ENDPOINT");
            return $content;
        }

        $contentObj = json_decode($content);
        $isJson = !(json_last_error() === JSON_ERROR_SYNTAX);
        if ($isJson) {
            $this->logger->log("JSON CONTENT: " . $content);
            if ($this->settings['parse_json']) {
                $jsonParser = new ShortPixelJsonParser($this);
                $content = json_encode($jsonParser->parse($contentObj));
            }
            else {
                $changed = false;
                //if not parsing json, still replace inside first level html properties.
                if(is_object($contentObj) || is_array($contentObj)) { //primitive types as 'lala' or 10 can also be JSON, can't iterate over these.
                    foreach($contentObj as $key => $value) {
                        if(preg_match('/^([\s↵]*(<!--[^>]+-->)*)*<\w*(\s[^>]*|)>/s', $value)) {
                            $contentObj->$key = $this->parser->parse($value);
                            $changed = true;
                        }
                    }
                }
                if($changed) {
                    //$this->logger->log(' AJAX - recording affected tags ', $this->affectedTags);
                    $this->recordAffectedTags($this->affectedTags);
                    $content = json_encode($contentObj);
                } else {
                    $this->logger->log("MISSING HTML");
                }
            }
        } elseif($this->spaiJSDequeued) {
            //TODO in cazul asta vom inlocui direct cu URL-urile finale ca AO
        } else {
            $content = $this->parser->parse($content);
        }

        return $content;
    }

    /*    public function replace_images_no_quotes ($matches) {
            if (strpos($matches[0], 'src=data:image/svg;u=') || count($matches) < 2){
                //avoid duplicated replaces due to filters interference
                return $matches[0];
            }
            return $this->_replace_images('src', $matches[0], $matches[1]);
        }*/

    public function maybe_cleanup($content)
    {
        $this->logger->log('CLEANUP: ' . preg_quote($this->settings['api_url'], '/'));
        return preg_replace_callback('/' . preg_quote($this->settings['api_url'], '/') . '.*?\/(https?):\/\//is',
            array($this, 'replace_api_url'), $content);
    }
    public function replace_api_url($matches) {
        return $matches[1] . '://';
    }

    public function replace_images_data_large_image($matches)
    {
        if (count($matches) < 3 || strpos($matches[0], 'data-large_image=' . $matches[1] . 'data:image/svg+xml;u=')) {
            //avoid duplicated replaces due to filters interference
            return $matches[0];
        }
        //$matches[1] will be either " or '
        return $this->_replace_images('data-large_image', $matches[0], $matches[2], $matches[1]);
    }


    public function replace_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if((function_exists('is_amp_endpoint') && is_amp_endpoint())) {
            return $sources;
        }
        $aspect = false;
        $this->logger->log("******** REPLACE IMAGE SRCSET: ", $sources);
        //return $sources;
        if($this->urlIsExcluded($image_src)) return $sources;
        if($this->settings['type'] == 1) return $sources; //not returning array() because the srcset is integrated and removed in full document parse;
        $pseudoSources = array();
        foreach ($sources as $key => $data) {
            if(strpos($data['url'], 'data:image/svg+xml;u=') === false) {
                if($this->urlIsExcluded($data['url'])) {
                    //if any of the items are excluded, don't replace
                    return $sources;
                }
                if($aspect === false) {
                    $sizes = ShortPixelUrlTools::get_image_size($image_src);
                    $aspect = $sizes[1] / $sizes[0];
                    $height = $sizes[1] > 1 ? $sizes[1] : 100;
                } else {
                    $height = round($key * $aspect);
                }
                $pseudoSources[$key] = array(
                    'url' => ShortPixelUrlTools::generate_placeholder_svg($key, $height, $data['url']),//$this->absoluteUrl($data['url'])),
                    'descriptor' => $data['descriptor'],
                    'value' => $data['value']);
            } else {
                $pseudoSources[$key] = $data;
            }
        }
        $this->logger->log("******** WITH: ", $pseudoSources);
        return $pseudoSources;
    }

    public function getActiveIntegrations() {
        if($this->integrations) {
            return $this->integrations;
        }
        $activePlugins = (array) apply_filters('active_plugins', get_option( 'active_plugins', array()));
        if ( is_multisite() ) {
            $activePlugins = array_merge($activePlugins, array_keys(get_site_option( 'active_sitewide_plugins')));
        }
            //test WPRocket
        if (false && in_array('wp-rocket/wp-rocket.php', $activePlugins)) {
            $path = dirname(dirname(__DIR__)) . '/wp-rocket/wp-rocket.php';
            $pluginVersion = $this->readPluginVersion($path);
            $wpRocketSettings = get_option('wp_rocket_settings', array());
            $hasCssFilter = (version_compare($pluginVersion, '3.4') >= 0);
            $rocket = array( 'lazyload' => (isset($wpRocketSettings['lazyload']) && $wpRocketSettings['lazyload'] == 1),
                             'css-filter' => $hasCssFilter,
                             'minify-css' => isset($wpRocketSettings['minify_css']) && $wpRocketSettings['minify_css'] == 1
                );
        } else {
            $rocket = array('lazyload' => false, 'css-filter' => false, 'minify-css' => false);
        }

        $this->integrations = array(
            'nextgen' => in_array('nextgen-gallery/nggallery.php', $activePlugins),
            'modula' => in_array('modula-best-grid-gallery/Modula.php', $activePlugins),
            'elementor' => in_array('elementor/elementor.php', $activePlugins),
            'elementor-addons' => in_array('essential-addons-for-elementor/essential_adons_elementor.php', $activePlugins) || in_array('essential-addons-for-elementor-lite/essential_adons_elementor.php', $activePlugins),
            'viba-portfolio' => in_array('viba-portfolio/viba-portfolio.php', $activePlugins),
            'envira' => in_array('envira-gallery/envira-gallery.php', $activePlugins) || in_array('envira-gallery-lite/envira-gallery-lite.php', $activePlugins),
            'everest' => in_array('everest-gallery/everest-gallery.php', $activePlugins) || in_array('everest-gallery-lite/everest-gallery-lite.php', $activePlugins),
            'wp-bakery' => in_array('js_composer/js_composer.php', $activePlugins), //WP Bakery (testimonials)
            'woocommerce' => in_array('woocommerce/woocommerce.php' , $activePlugins),
            'foo' => in_array('foogallery/foogallery.php', $activePlugins),
            'oxygen' => in_array( 'oxygen/functions.php', $activePlugins),
            'slider-revolution' => in_array('revslider/revslider.php', $activePlugins),
            'smart-slider' => in_array('smart-slider-3/smart-slider-3.php', $activePlugins) || in_array('nextend-smart-slider3-pro/nextend-smart-slider3-pro.php', $activePlugins),
            'wp-grid-builder' => in_array('wp-grid-builder/wp-grid-builder.php', $activePlugins),
            'wp-rocket' => $rocket
        );
        //test theme. 'Jupiter' 'CROWD 2'
        $theme = wp_get_theme();
        $this->integrations['theme'] = $theme->Name;
        if(SHORTPIXEL_AI_DEBUG) {
            //integration forced from the request parameters
            foreach($this->integrations as $key => $val) {
                if(isset($_REQUEST['spai_force_' . $key])) {
                    $this->integrations[$key] = true;
                }
            }
        }
        return $this->integrations;
    }

    public function readPluginVersion($path) {
        $ver = '0.0';
        if(file_exists($path)) {
            $fp = fopen($path, 'r');
            for($i = 0; $i < 100 && !feof($fp); $i++) {
                $line = trim(fgets($fp));
                if(strpos($line, 'Version:')) {
                    $version = explode('Version:', $line);
                    $ver = trim(end($version));
                    break;
                }
            }
            fclose($fp);
        }
        return $ver;
    }

    public function getTagRules() {
        if ($this->integrations === false) {
            $this->integrations = $this->getActiveIntegrations();
        }

        $regexItems = array(
            array('img|div', 'data-src'), //CHANGED ORDER for images which have both src and data-src - TODO better solution
            array('img|amp-img', 'src', false, false, ($this->settings['type'] == 1 ? 'srcset' : false), false, false, $this->settings['ext_meta'] == '1'),
            //fifth param instructs to integrate that attribute into the second, for method 1 (src) we integrate of srcset
            //\-> The given fifth attribute should have the exact structure of srcset.
            // eighth param - extMeta (default false)
            array('img', 'data-large-image'),
            array('a', 'href', 'media-gallery-link'), //this one seems generally related to sliders, see HS 972394549
            array('link', 'href', false, 'rel', false, 'icon', true) //sixth param together with fourth filters by attribute value, seventh param isEager
        );
        if ($this->integrations['oxygen']) {
            $regexItems[] = array('a', 'href', 'oxy-gallery-item', false, false, false, true);
            array_unshift($regexItems, array('img', 'data-original-src', false, false, false, false, true));
        }
        if ($this->integrations['nextgen']) {
            $regexItems[1] = array('img|div|a', 'data-src');
            $regexItems[] = array('a', 'data-thumbnail');
        }
        if ($this->integrations['modula']) {
            $regexItems[] = array('a', 'href', false, 'data-lightbox'); //fourth param filters by attribute
            $regexItems[] = array('a', 'href', false, 'data-fancybox');
        }
        if ($this->integrations['elementor']) {
            $regexItems[] = array('a', 'href', false, 'data-elementor-open-lightbox'); //fourth param filters by attribute
            $regexItems[] = array('a', 'href', 'viba-portfolio-media-link'); //third param filters by class
        }
        if ($this->integrations['elementor-addons']) {
            $regexItems[] = array('a', 'href', 'eael-magnific-link'); //fourth param filters by attribute
        }
        if ($this->integrations['viba-portfolio'] && !$this->integrations['elementor']) {
            $regexItems[] = array('a', 'href', 'viba-portfolio-media-link'); //third param filters by class
        }
        if ($this->integrations['slider-revolution']) {
            $regexItems[] = array('img', 'data-lazyload', false, false, false, false, true);
        }
        if ($this->integrations['envira']) {
            $regexItems[] = array('img', 'data-envira-src');
            $regexItems[] = array('img', 'data-safe-src');
            $regexItems[] = array('a', 'href', 'envira-gallery-link'); //third param filters by class
        }
        if ($this->integrations['everest']) {
            $regexItems[] = array('a', 'href', false, 'data-lightbox-type'); //fourth param filters by attribute
        }
        if ($this->integrations['wp-bakery']) {
            $regexItems[] = array('span', 'data-element-bg', 'dima-testimonial-image');  //third param filters by class
        }
        if ($this->integrations['foo']) {
            $regexItems[] = array('img', 'data-src-fg', 'fg-image');
            $regexItems[] = array('a', 'href', 'fg-thumb', 'data-attachment-id');
        }
        if ($this->integrations['smart-slider']) {
            $regexItems[] = array('div', 'data-desktop', 'n2-ss-slide-background-image');  //third param filters by class
        }
        if ($this->integrations['wp-grid-builder']) {
            $regexItems[] = array('div', 'data-wpgb-src');
        }

        if($this->settings['parse_css_files'] && !($this->integrations['wp-rocket']['minify-css'] && $this->integrations['wp-rocket']['css-filter'])) {
            $this->logger->log("CSS FILES TO CDN");
            $regexItems[] = array('link', 'href', false, 'rel', false, 'stylesheet', true);
        }

        $this->logger->log("TAG RULES: ", $regexItems);
        return $regexItems;
    }

    public function getTagRulesMap() {
        $rules = $this->getTagRules();
        $tree = array();
        foreach($rules as $rule) {
            $tags = explode("|", $rule[0]);
            foreach($tags as $tag) {
                if(!isset($tree[$tag])) {
                    $tree[$tag] = array();
                }
                $ruleNode = array('attr' => $rule[1]);
                $ruleNode['classFilter'] = isset($rule[2]) ? $rule[2] : false;
                $ruleNode['attrFilter'] = isset($rule[3]) ? $rule[3] : false;
                $ruleNode['attrValFilter'] = isset($rule[5]) ? $rule[5] : false;
                $ruleNode['mergeAttr'] = isset($rule[4]) ? $rule[4] : false;
                $ruleNode['lazy'] = !isset($rule[6]) || ! $rule[6]? true : false;
                $ruleNode['extMeta'] = !isset($rule[7]) || ! $rule[7]? true : false;
                $tree[$tag][] = (object)$ruleNode;
            }
        }
        //add also the rule for bakground image
        $tree['*'] = array((object)array('attr' => 'style', 'lazy' => false, 'customReplacer' => array(&$this->cssParser, 'replace_in_tag_style_backgrounds')));
        return $tree;
    }

    public function getExceptionsMap() {
        return (object)array(
            'excluded_selectors' => $this->splitSelectors($this->settings['excluded_selectors']),
            'eager_selectors' => $this->splitSelectors($this->settings['eager_selectors']),
            'noresize_selectors' => $this->splitSelectors($this->settings['noresize_selectors']),
            'excluded_paths' => $this->splitSelectors($this->settings['excluded_paths']));
    }

    public function tagIs($type, $text) {
        //could be excluded_selectors or noresize_selectors
        if(   isset($this->settings[$type . '_selectors']) && strlen($this->settings[$type . '_selectors'])
            && (strpos($text, 'class=') !== false || strpos($text, 'id=') !== false)) {
            foreach(explode(',', $this->settings[$type . '_selectors']) as $selector) {
                $selector = trim($selector);
                $parts = explode('.', $selector);
                if(count($parts) == 2 && ( $parts[0] == '' || strpos($text, $parts[0]) === 1)) {
                    if(preg_match('/\sclass=[\'"]([-_a-zA-Z0-9\s]*[\s]+' . $parts[1] . '|' . $parts[1] . ')[\'"\s]/i', $text)) {
                        return true;
                    }
                    elseif(preg_match('/\sclass=' . $parts[1] . '[>\s]/i', $text)) {
                        return true;
                    }
                } else {
                    $parts = explode('#', $selector);
                    if(count($parts) == 2 && ($parts[0] == '' || strpos($text, $parts[0]) === 1)) {
                        if(preg_match('/\sid=[\'"]' . $parts[1] . '[\'"\s]/i', $text)) {
                            return true;
                        }
                    }
                }
            }

        }
        return false;
    }

        public function urlIsApi($url) {
        $parsed = parse_url($url);
        $parsedApi = parse_url($this->settings['api_url']);
        return isset($parsed['host']) && $parsed['host'] === $parsedApi['host'];
    }

    public function urlIsExcluded($url) {
        //$this->logger->log("IS EXCLUDED? $url");
        if( isset($this->settings['excluded_paths']) && strlen($this->settings['excluded_paths'])) {
            foreach (explode("\n", $this->settings['excluded_paths']) as $rule) {

                $rule = explode(':', $rule);
                if(count($rule) >= 2) {
                    $type = array_shift($rule);
                    $value = implode(':', $rule);
                    $value = trim($value); //remove whitespaces and especially the \r which gets added on Windows (most probably)

                    switch($type) {
                        case 'regex':
                            if(@preg_match($value, $url)) {
                                $this->logger->log("EXCLUDED by $type : $value");
                                return true;
                            }
                            break;
                        case 'path':
                        case 'http': //being so kind to accept urls as they are. :)
                        case 'https':
                            if(strpos($url, $value) !== false) {
                                $this->logger->log("EXCLUDED by $type : $value");
                                return true;
                            }
                            break;
                    }
                }
            }
        }
        //$this->logger->log("NOT EXCLUDED");
        return false;
    }

    /**
     * @return bool true if SPAI is welcome ( not welcome for example if it's an AMP page, CLI, is admin page or PageSpeed is off )
     */
    protected function isWelcome() {
        if(isset($_SERVER['HTTP_REFERER'])) {
            $admin = parse_url(admin_url());
            $referrer = parse_url($_SERVER['HTTP_REFERER']);
            //don't act on pages being customized (wp-admin/customize.php)
            if(isset($referrer['path']) && ($referrer['path'] === $admin['path'] . 'customize.php' || $referrer['path'] === $admin['path'] . 'post.php')) {
                return false;
            }
            elseif($this->doingAjax && $admin['host'] == $referrer['host'] && strpos($referrer['path'], $admin['path']) === 0) {
                return false;
            }
        }
        return !(is_feed()
             || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
             || (defined('DOING_CRON') && DOING_CRON)
             || (defined('WP_CLI') && WP_CLI)
             || (isset($_GET['PageSpeed']) && $_GET['PageSpeed'] == 'off')
             || (isset($_GET['tve']) && $_GET['tve'] == 'true') //Thrive Architect editor (thrive-visual-editor/thrive-visual-editor.php)
             || (isset($_GET['ct_builder']) && $_GET['ct_builder'] == 'true' && isset($_GET['oxygen_iframe']) && $_GET['oxygen_iframe'] == 'true') //Oxygen Builder
            //                                                                  Woo product variations       avia layout builder AJAX call
             || (isset($_POST['action']) && in_array($_POST['action'], array('woocommerce_load_variations', 'avia_ajax_text_to_interface', 'avia_ajax_text_to_preview')) )
             || (is_admin() && function_exists("is_user_logged_in") && is_user_logged_in()
                && !$this->doingAjax)
            );
    }

    public function recordAffectedTags($tags) {
        update_option('spai_settings_lazy_ajax_tags', $this->mergeTags($tags, get_option('spai_settings_lazy_ajax_tags', array())));
    }

    public function getRecordedAffectedTags() {
        return get_option('spai_settings_lazy_ajax_tags', array());
    }

    public function getAllAffectedTags() {
        return $this->mergeTags($this->affectedTags, get_option('spai_settings_lazy_ajax_tags', array()));
    }

    protected function mergeTags($tags, $moreTags) {
        foreach($tags as $key => $val) {
            $moreTags[$key] |= $val;
        }
        return $moreTags;
    }
}
