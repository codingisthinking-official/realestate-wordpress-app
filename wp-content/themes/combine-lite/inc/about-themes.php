<?php
/**
 *Combine Lite About Theme
 *
 * @package Combine Lite
 */

//about theme info
add_action( 'admin_menu', 'combine_lite_abouttheme' );
function combine_lite_abouttheme() {    	
	add_theme_page( __('About Theme Info', 'combine-lite'), __('About Theme Info', 'combine-lite'), 'edit_theme_options', 'combine_lite_guide', 'combine_lite_mostrar_guide');   
} 

//Info of the theme
function combine_lite_mostrar_guide() { 	
?>
<div class="wrap-GT">
	<div class="gt-left">
   		   <div class="heading-gt">
			  <h3><?php esc_html_e('About Theme Info', 'combine-lite'); ?></h3>
		   </div>
          <p><?php esc_html_e('Combine Lite  is a Free highend WordPress theme designed specially to accommodate the requirements of a high end and luxury websites. This theme has amazing multipurpose functionality and can be used for business, corporate, personal, blog, photography, construction, hotel and any other kind of multipurpose website. ','combine-lite'); ?></p>
<div class="heading-gt"> <?php esc_html_e('Theme Features', 'combine-lite'); ?></div>
 

<div class="col-2">
  <h4><?php esc_html_e('Theme Customizer', 'combine-lite'); ?></h4>
  <div class="description"><?php esc_html_e('The built-in customizer panel quickly change aspects of the design and display changes live before saving them.', 'combine-lite'); ?></div>
</div>

<div class="col-2">
  <h4><?php esc_html_e('Responsive Ready', 'combine-lite'); ?></h4>
  <div class="description"><?php esc_html_e('The themes layout will automatically adjust and fit on any screen resolution and looks great on any device. Fully optimized for iPhone and iPad.', 'combine-lite'); ?></div>
</div>

<div class="col-2">
<h4><?php esc_html_e('Cross Browser Compatible', 'combine-lite'); ?></h4>
<div class="description"><?php esc_html_e('Our themes are tested in all mordern web browsers and compatible with the latest version including Chrome,Firefox, Safari, Opera, IE11 and above.', 'combine-lite'); ?></div>
</div>

<div class="col-2">
<h4><?php esc_html_e('E-commerce', 'combine-lite'); ?></h4>
<div class="description"><?php esc_html_e('Fully compatible with WooCommerce plugin. Just install the plugin and turn your site into a full featured online shop and start selling products.', 'combine-lite'); ?></div>
</div>
<hr />  
</div><!-- .gt-left -->
	
<div class="gt-right">			
        <div>				
            <a href="<?php echo esc_url( COMBINE_LITE_LIVE_DEMO ); ?>" target="_blank"><?php esc_html_e('Live Demo', 'combine-lite'); ?></a> | 
            <a href="<?php echo esc_url( COMBINE_LITE_PROTHEME_URL ); ?>" target="_blank"><?php esc_html_e('Purchase Pro', 'combine-lite'); ?></a> | 
            <a href="<?php echo esc_url( COMBINE_LITE_THEME_DOC ); ?>" target="_blank"><?php esc_html_e('Documentation', 'combine-lite'); ?></a>
        </div>		
</div><!-- .gt-right-->
<div class="clear"></div>
</div><!-- .wrap-GT -->
<?php } ?>