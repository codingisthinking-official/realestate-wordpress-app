<?php    
/**
 *Combine Lite Theme Customizer
 *
 * @package Combine Lite
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function combine_lite_customize_register( $wp_customize ) {	
	
	function combine_lite_sanitize_dropdown_pages( $page_id, $setting ) {
	  // Ensure $input is an absolute integer.
	  $page_id = absint( $page_id );
	
	  // If $page_id is an ID of a published page, return it; otherwise, return the default.
	  return ( 'publish' == get_post_status( $page_id ) ? $page_id : $setting->default );
	}

	function combine_lite_sanitize_checkbox( $checked ) {
		// Boolean check.
		return ( ( isset( $checked ) && true == $checked ) ? true : false );
	}  
		
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	
	 //Panel for section & control
	$wp_customize->add_panel( 'combine_lite_panel_area', array(
		'priority' => null,
		'capability' => 'edit_theme_options',
		'theme_supports' => '',
		'title' => __( 'Theme Options Panel', 'combine-lite' ),		
	) );
	
	//Layout Options
	$wp_customize->add_section('layout_option',array(
		'title' => __('Site Layout','combine-lite'),			
		'priority' => 1,
		'panel' => 	'combine_lite_panel_area',          
	));		
	
	$wp_customize->add_setting('sitebox_layout',array(
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
	));	 

	$wp_customize->add_control( 'sitebox_layout', array(
    	'section'   => 'layout_option',    	 
		'label' => __('Check to Box Layout','combine-lite'),
		'description' => __('If you want to box layout please check the Box Layout Option.','combine-lite'),
    	'type'      => 'checkbox'
     )); //Layout Section 
	
	$wp_customize->add_setting('combine_lite_color_scheme',array(
		'default' => '#2edaff',
		'sanitize_callback' => 'sanitize_hex_color'
	));
	
	$wp_customize->add_control(
		new WP_Customize_Color_Control($wp_customize,'combine_lite_color_scheme',array(
			'label' => __('Color Scheme','combine-lite'),			
			'description' => __('More color options in PRO Version','combine-lite'),
			'section' => 'colors',
			'settings' => 'combine_lite_color_scheme'
		))
	);	
	
	//Header Contact Info
	$wp_customize->add_section('hdr_contactinfo_sec',array(
		'title' => __('Header Contact Info','combine-lite'),				
		'priority' => null,
		'panel' => 	'combine_lite_panel_area',
	));
	
	$wp_customize->add_setting('hdr_phone_text',array(
		'default' => null,
		'sanitize_callback' => 'sanitize_text_field'	
	));
	
	$wp_customize->add_control('hdr_phone_text',array(	
		'type' => 'text',
		'label' => __('Add phone number here','combine-lite'),
		'section' => 'hdr_contactinfo_sec',
		'setting' => 'hdr_phone_text'
	));	
	
	$wp_customize->add_setting('contact_emailid',array(
		'sanitize_callback' => 'sanitize_email'
	));
	
	$wp_customize->add_control('contact_emailid',array(
		'type' => 'text',
		'label' => __('Add email address here.','combine-lite'),
		'section' => 'hdr_contactinfo_sec'
	));	
	
	$wp_customize->add_setting('combine_lite_show_hdrcontactinfo',array(
		'default' => false,
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
		'capability' => 'edit_theme_options',
	));	 
	
	$wp_customize->add_control( 'combine_lite_show_hdrcontactinfo', array(
	   'settings' => 'combine_lite_show_hdrcontactinfo',
	   'section'   => 'hdr_contactinfo_sec',
	   'label'     => __('Check To show This Section','combine-lite'),
	   'type'      => 'checkbox'
	 ));//Show header contact info
	
	
	 
	 //Header social icons
	$wp_customize->add_section('combine_lite_social_section',array(
		'title' => __('Header social icons','combine-lite'),
		'description' => __( 'Add social icons link here to display icons in header', 'combine-lite' ),			
		'priority' => null,
		'panel' => 	'combine_lite_panel_area', 
	));
	
	$wp_customize->add_setting('combine_lite_fb_link',array(
		'default' => null,
		'sanitize_callback' => 'esc_url_raw'	
	));
	
	$wp_customize->add_control('combine_lite_fb_link',array(
		'label' => __('Add facebook link here','combine-lite'),
		'section' => 'combine_lite_social_section',
		'setting' => 'combine_lite_fb_link'
	));	
	
	$wp_customize->add_setting('combine_lite_twitt_link',array(
		'default' => null,
		'sanitize_callback' => 'esc_url_raw'
	));
	
	$wp_customize->add_control('combine_lite_twitt_link',array(
		'label' => __('Add twitter link here','combine-lite'),
		'section' => 'combine_lite_social_section',
		'setting' => 'combine_lite_twitt_link'
	));
	
	$wp_customize->add_setting('combine_lite_gplus_link',array(
		'default' => null,
		'sanitize_callback' => 'esc_url_raw'
	));
	
	$wp_customize->add_control('combine_lite_gplus_link',array(
		'label' => __('Add google plus link here','combine-lite'),
		'section' => 'combine_lite_social_section',
		'setting' => 'combine_lite_gplus_link'
	));
	
	$wp_customize->add_setting('combine_lite_linked_link',array(
		'default' => null,
		'sanitize_callback' => 'esc_url_raw'
	));
	
	$wp_customize->add_control('combine_lite_linked_link',array(
		'label' => __('Add linkedin link here','combine-lite'),
		'section' => 'combine_lite_social_section',
		'setting' => 'combine_lite_linked_link'
	));
	
	$wp_customize->add_setting('combine_lite_show_socialicons',array(
		'default' => false,
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
		'capability' => 'edit_theme_options',
	));	 
	
	$wp_customize->add_control( 'combine_lite_show_socialicons', array(
	   'settings' => 'combine_lite_show_socialicons',
	   'section'   => 'combine_lite_social_section',
	   'label'     => __('Check To show This Section','combine-lite'),
	   'type'      => 'checkbox'
	 ));//Show Header Social icons Section 			
	
	// Slider Section		
	$wp_customize->add_section( 'combine_lite_slider_options', array(
		'title' => __('Slider Section', 'combine-lite'),
		'priority' => null,
		'description' => __('Default image size for slider is 1400 x 645 pixel.','combine-lite'), 
		'panel' => 	'combine_lite_panel_area',           			
    ));
	
	$wp_customize->add_setting('combine_lite_sliderpage1',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
	
	$wp_customize->add_control('combine_lite_sliderpage1',array(
		'type' => 'dropdown-pages',
		'label' => __('Select page for slide one:','combine-lite'),
		'section' => 'combine_lite_slider_options'
	));	
	
	$wp_customize->add_setting('combine_lite_sliderpage2',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
	
	$wp_customize->add_control('combine_lite_sliderpage2',array(
		'type' => 'dropdown-pages',
		'label' => __('Select page for slide two:','combine-lite'),
		'section' => 'combine_lite_slider_options'
	));	
	
	$wp_customize->add_setting('combine_lite_sliderpage3',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
	
	$wp_customize->add_control('combine_lite_sliderpage3',array(
		'type' => 'dropdown-pages',
		'label' => __('Select page for slide three:','combine-lite'),
		'section' => 'combine_lite_slider_options'
	));	// Slider Section	
	
	$wp_customize->add_setting('combine_lite_slider_readmore',array(
		'default' => null,
		'sanitize_callback' => 'sanitize_text_field'	
	));
	
	$wp_customize->add_control('combine_lite_slider_readmore',array(	
		'type' => 'text',
		'label' => __('Add slider Read more button name here','combine-lite'),
		'section' => 'combine_lite_slider_options',
		'setting' => 'combine_lite_slider_readmore'
	)); // Slider Read More Button Text
	
	$wp_customize->add_setting('combine_lite_show_slider',array(
		'default' => false,
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
		'capability' => 'edit_theme_options',
	));	 
	
	$wp_customize->add_control( 'combine_lite_show_slider', array(
	    'settings' => 'combine_lite_show_slider',
	    'section'   => 'combine_lite_slider_options',
	     'label'     => __('Check To Show This Section','combine-lite'),
	   'type'      => 'checkbox'
	 ));//Show Slider Section	
	 
	 // Our Story section 
	$wp_customize->add_section('combine_lite_secondsection', array(
		'title' => __('Our Story Section','combine-lite'),
		'description' => __('Select Pages from the dropdown for our story section','combine-lite'),
		'priority' => null,
		'panel' => 	'combine_lite_panel_area',          
	));		
	
	$wp_customize->add_setting('combine_lite_ourstory_page',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
 
	$wp_customize->add_control(	'combine_lite_ourstory_page',array(
		'type' => 'dropdown-pages',			
		'section' => 'combine_lite_secondsection',
	));		
	
	$wp_customize->add_setting('show_combine_lite_ourstory_page',array(
		'default' => false,
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
		'capability' => 'edit_theme_options',
	));	 
	
	$wp_customize->add_control( 'show_combine_lite_ourstory_page', array(
	    'settings' => 'show_combine_lite_ourstory_page',
	    'section'   => 'combine_lite_secondsection',
	    'label'     => __('Check To Show This Section','combine-lite'),
	    'type'      => 'checkbox'
	));//Show Our Story Section 
	 
	 
	 // three column Services section
	$wp_customize->add_section('combine_lite_services_section', array(
		'title' => __('3 Column Services Section','combine-lite'),
		'description' => __('Select pages from the dropdown for services section','combine-lite'),
		'priority' => null,
		'panel' => 	'combine_lite_panel_area',          
	));	
	
	$wp_customize->add_setting('combine_lite_services_pagebx1',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
 
	$wp_customize->add_control(	'combine_lite_services_pagebx1',array(
		'type' => 'dropdown-pages',			
		'section' => 'combine_lite_services_section',
	));		
	
	$wp_customize->add_setting('combine_lite_services_pagebx2',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
 
	$wp_customize->add_control(	'combine_lite_services_pagebx2',array(
		'type' => 'dropdown-pages',			
		'section' => 'combine_lite_services_section',
	));
	
	$wp_customize->add_setting('combine_lite_services_pagebx3',array(
		'default' => '0',			
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'combine_lite_sanitize_dropdown_pages'
	));
 
	$wp_customize->add_control(	'combine_lite_services_pagebx3',array(
		'type' => 'dropdown-pages',			
		'section' => 'combine_lite_services_section',
	));
	
	
	$wp_customize->add_setting('combine_lite_show_services_section',array(
		'default' => false,
		'sanitize_callback' => 'combine_lite_sanitize_checkbox',
		'capability' => 'edit_theme_options',
	));	 
	
	$wp_customize->add_control( 'combine_lite_show_services_section', array(
	   'settings' => 'combine_lite_show_services_section',
	   'section'   => 'combine_lite_services_section',
	   'label'     => __('Check To Show This Section','combine-lite'),
	   'type'      => 'checkbox'
	 ));//Show services section
	 
	
		 
}
add_action( 'customize_register', 'combine_lite_customize_register' );

function combine_lite_custom_css(){ 
?>
	<style type="text/css"> 					
        a, .poststyle_listing h2 a:hover,
        #sidebar ul li a:hover,								
        .poststyle_listing h3 a:hover,					
        .recent-post h6:hover,	
		.social-icons a:hover,				
        .services_3col_box:hover .button,									
        .postmeta a:hover,
        .button:hover,
		.ourstory_content_column h3 span,
        .footercolumn ul li a:hover, 
        .footercolumn ul li.current_page_item a,      
        .services_3col_box:hover h3 a,		
        .header-top a:hover,
		.footer-wrapper h2 span,
		.footer-wrapper ul li a:hover, 
		.footer-wrapper ul li.current_page_item a,
        .sitenav ul li a:hover, 
        .sitenav ul li.current-menu-item a,
        .sitenav ul li.current-menu-parent a.parent,
        .sitenav ul li.current-menu-item ul.sub-menu li a:hover				
            { color:<?php echo esc_html( get_theme_mod('combine_lite_color_scheme','#2edaff')); ?>;}					 
            
        .pagination ul li .current, .pagination ul li a:hover, 
        #commentform input#submit:hover,					
        .nivo-controlNav a.active,
        .learnmore,	
		.services_3col_box .services_imgcolumn,	
		.nivo-caption .slide_more,											
        #sidebar .search-form input.search-submit,				
        .wpcf7 input[type='submit'],				
        nav.pagination .page-numbers.current,       		
        .toggle a	
            { background-color:<?php echo esc_html( get_theme_mod('combine_lite_color_scheme','#2edaff')); ?>;}	
			
		.ourstory_imagebox img:hover	
            { box-shadow: 0 0 0 10px <?php echo esc_html( get_theme_mod('combine_lite_color_scheme','#2edaff')); ?>;}		
         	
    </style> 
<?php                          
}
         
add_action('wp_head','combine_lite_custom_css');	 

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function combine_lite_customize_preview_js() {
	wp_enqueue_script( 'combine_lite_customizer', get_template_directory_uri() . '/js/customize-preview.js', array( 'customize-preview' ), '20171016', true );
}
add_action( 'customize_preview_init', 'combine_lite_customize_preview_js' );