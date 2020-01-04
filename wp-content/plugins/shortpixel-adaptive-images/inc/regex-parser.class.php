<?php
/**
 * User: simon
 * Date: 10.06.2019
 */

class ShortPixelRegexParser {
    protected $ctrl;
    private $logger;
    private $cssParser;

    private $scripts;
    private $styles;
    private $CDATAs;
    private $noscripts;

    private $classFilter = false;
    private $attrFilter = false;
    private $attrValFilter = false;
    private $attrToIntegrateAndRemove = false;

    private $isEager = false;

    public function __construct(ShortPixelAI $ctrl)
    {
        $this->ctrl = $ctrl;
        $this->logger = ShortPixelAILogger::instance();
        $this->cssParser = new ShortPixelCssParser($ctrl);
    }

    public function parse($content) {
        $this->logger->log("******** REGEX PARSER *********");

        // EXTRACT all CDATA and inline script to be reinserted after the replaces
        // -----------------------------------------------------------------------

        $this->CDATAs = array();
        // this ungreedy regex fails with catastrophic backtracking if the CDATA is very long so will do it the manual way...
        /*$content = preg_replace_callback(
        //     this part matches the scripts of the page, we don't replace inside JS
            '/\<\!\[CDATA\[(.*)\]\]\>/sU', // U flag - UNGREEDY
            array($this, 'replace_cdatas'),
            $content
        );*/
        //$content = $this->replace_cdatas($content);
        $content = $this->extract_blocks($content, '__sp_cdata', '<![CDATA[', ']]>', $this->CDATAs, true);

        //<noscript> blocks will have URLs replaced but eagerly
        $this->noscripts = array();
        $content = $this->extract_blocks($content, '__sp_noscript', '<noscript>', '</noscript>', $this->noscripts);

        $this->scripts = array();
        $content = preg_replace_callback(
        //     this part matches the scripts of the page, we don't replace inside JS
            '/\<script(.*)\<\/script\>/sU', // U flag - UNGREEDY
            array($this, 'replace_scripts'),
            $content
        );
        $this->styles = array();
        $content = $this->extract_blocks($content, '__sp_style', '<style.', '</style>', $this->styles);
/*        $content = preg_replace_callback(
        //     this part matches the styles of the page, we replace inside CSS afterwards.
            '/\<style(.*)\<\/style\>/sU', // U flag - UNGREEDY
            array($this, 'replace_styles'),
            $content
        );
*/

        $this->logger->log("CHECK 1: " . strlen($content) );

        // Replace different cases of image URL usages
        // -------------------------------------------

        /* $content = preg_replace_callback(
        //     this part matches URLs without quotes
            '/\<img[^\<\>]*?\ssrc\=([^\s\'"][^\s>]*)(?:.+?)\>/s',
            array( $this, 'replace_images_no_quotes' ),
            $content
        ); */

        $regexMaster = '/\<({{TAG}})(?:\s|\s[^\<\>]*?\s)({{ATTR}})\=(?:(\"|\')([^\>\'\"]+)(?:\'|\")|([^\>\'\"\s]+))(?:.*?)\>/s';
        $regexMasterSrcset = '/\<({{TAG}})(?:\s|\s[^\<\>]*?\s)({{ATTR}})\=(\"|\')([^\>]+?)(?:\3)(?:.*?)\>/s';
        $regexItems = $this->ctrl->getTagRules();

        foreach ($regexItems as $regexItem) {
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array($regexItem[0], $regexItem[1]), $regexMaster);
            //$this->logger->log("REGEX: $regex");
            $this->classFilter = (isset($regexItem[2])) ? $regexItem[2] : false;
            $this->attrFilter = (isset($regexItem[3])) ? $regexItem[3] : false;
            $this->attrValFilter = (isset($regexItem[5])) ? $regexItem[5] : false;
            $this->attrToIntegrateAndRemove = (isset($regexItem[4])) ? $regexItem[4] : false;
            $this->isEager = (isset($regexItem[6])) ? $regexItem[6] : false;
            $this->extMeta = (isset($regexItem[7])) ? $regexItem[7] : false;
            $content = preg_replace_callback($regex,
                array($this, 'replace_images'),
                $content
            );
            $this->classFilter = false;
            $this->attrFilter = false;
        }

        $this->logger->log("******** REGEX PARSER replace_wc_gallery_thumbs *********");

        $content = preg_replace_callback(
            '/\<div[^\<\>]*?\sdata-thumb\=(?:\"|\')(.+?)(?:\"|\')(?:.+?)\>\<\/div\>/s',
            array($this, 'replace_wc_gallery_thumbs'),
            $content
        );

        $this->logger->log("******** REGEX PARSER replace_background_image_from_tag *********");

        $content = preg_replace_callback(
            '/\<([\w]+)(?:[^\<\>]*?)\b(background-image|background)(\s*:(?:[^;]*?[,\s]|\s*))url\((?:\'|")?([^\'"\)]*)(\'|")?\s*\).*?\>/s',
            array($this->cssParser, 'replace_background_image_from_tag'),
            $content
        );

        $integrations = $this->ctrl->getActiveIntegrations();

        if($integrations['theme'] == 'CROWD 2') {
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('div|a', 'style'), $regexMasterSrcset);
            $this->logger->log("CROWD 2 theme - regex $regex");
            $content = preg_replace_callback($regex,
                array($this, 'replace_crowd2_img_styles'),
                $content
            );
        }

            $integrations = $this->ctrl->getActiveIntegrations();
        if($integrations['wp-bakery']) {
            $content = preg_replace_callback(
                '/\<([\w]+)(?:[^\<\>]*?)\b(data-ultimate-bg)(=(?:"|\'|)[^"\']*?)url\((?:\'|")?([^\'"\)]*)(\'|")?\s*\)/s',
                array($this->cssParser, 'replace_wp_bakery_data_ultimate_bg'),
                $content
            );
        }

        $this->logger->log("******** REGEX PARSER getActiveIntegrations returns:  ", $integrations);

        if($this->ctrl->settings['type'] !== 1) { //srcset has to be checked too, in some cases the srcset wp hook isn't called...
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('img', 'srcset'), $regexMasterSrcset);
            $this->logger->log("REGEX: $regex");
            $content = preg_replace_callback($regex,
                array($this, 'replace_custom_srcset'),
                $content
            );
        }

        if ($integrations['envira']) {
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('img', 'data-envira-srcset'), $regexMasterSrcset);
            $this->logger->log("REGEX: $regex");
            $content = preg_replace_callback($regex,
                array($this, 'replace_custom_srcset'),
                $content
            );
        }

        if($integrations['theme'] == 'Jupiter') {
            //Jupiter has the mk slider in it, which uses srcsets encoded as JSON in data-mk-image-src-set atributes. How cool is that.
            $this->logger->log("Jupiter data-mk: $regex");
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('img', 'data-mk-image-src-set'), $regexMasterSrcset);
            $content = preg_replace_callback($regex,
                array($this, 'replace_custom_json_attr'),
                $content
            );
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('div', 'data-mk-img-set'), $regexMasterSrcset);
            $content = preg_replace_callback($regex,
                array($this, 'replace_custom_json_attr'),
                $content
            );
        } elseif($integrations['theme'] == 'Stack') {
            // Stack moves srcs from images to background-images of divs...
            $this->ctrl->affectedTags['div'] |= 2;
        }

        if($integrations['elementor']) {
            //Elementor can pass image URLs in sections' data-settings
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('section', 'data-settings'), $regexMasterSrcset);
            $content = preg_replace_callback($regex,
                array($this, 'replace_custom_json_attr'),
                $content
            );
        }

        if($integrations['woocommerce'] && preg_match('/\<form\b[^>]+\bdata-product_variations=/is', $content)) {
            //there are product variations
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('form', 'data-product_variations'), $regexMasterSrcset);
            $content = preg_replace_callback($regex,
                array($this, 'replace_product_variations'),
                $content
            );
        }

        /*if($this->integrations['modula']) {
            $regex = str_replace(array('{{TAG}}','{{ATTR}}'), array('img', 'data-envira-srcset'), $regexMasterSrcset);
            $this->logger->log("REGEX: $regex");
            $content = preg_replace_callback( $regex,
                array( $this, 'replace_custom_srcset' ),
                $content
            );
        }*/

        //NextGen uses a data-src and data-thumbnail inside the <a> tag for the image popup
        /*        $content = preg_replace_callback(
                    '/\<(?:a|div)[^\<\>]*?\sdata-src\=(\"|\'?)(.+?)(?:\"|\')(?:.+?)\>/s',
                    array( $this, 'replace_images_data_src' ),
                    $content
                );
                $content = preg_replace_callback(
                    '/\<a[^\<\>]*?\sdata-thumbnail\=(\"|\'?)(.+?)(?:\"|\')(?:.+?)\>/s',
                    array( $this, 'replace_link_data_thumbnail' ),
                    $content
                );
        */
        //TODO this is not working because NextGen is not handling the inline data: images properly
        //TODO check with them
        if ($integrations['nextgen']) {
            $content = preg_replace_callback(
                '/\<a[^\<\>]*?\shref\=(\"|\'?)(.+?)(?:\"|\')(?:.+?)\>/s',
                array($this, 'replace_link_href'),
                $content
            );
        }

        //$content = preg_replace_callback(
        //	'/\<div.+?data-src\=(?:\"|\')(.+?)(?:\"|\')(?:.+?)\>\<\/div\>/s',
        //	array( $this, 'replace_wc_gallery_thumbs' ),
        //	$content
        //);

        //put back the styles, scripts and CDATAs.
        for ($i = 0; $i < count($this->styles); $i++) {
            $style = $this->styles[$i];
            //$this->logger->log("STYLE $i: $style");
            //replace all the background-image's
            $style = $this->cssParser->replace_inline_style_backgrounds($style);
            //$this->logger->log("STYLE REPLACED: $style");

            $content = str_replace("<style>__sp_style_plAc3h0ldR_$i</style>", $style, $content);
        }

        //handle the noscripts in a simpler (only <img>) and eager way.
        for ($i = 0; $i < count($this->noscripts); $i++) {
            $noscript = $this->noscripts[$i];
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('img', 'src'), $regexMaster);
            $this->logger->log("NOSCRIPT: $noscript");
            $this->classFilter = false;
            $this->attrFilter = false;
            $this->attrValFilter = false;
            $this->attrToIntegrateAndRemove = false;
            $this->isEager = true;
            $this->extMeta = false;
            $noscript = preg_replace_callback($regex,
                array($this, 'replace_images'),
                $noscript
            );
            $this->logger->log("NOSCRIPT SRC: $noscript");
            $regex = str_replace(array('{{TAG}}', '{{ATTR}}'), array('img', 'srcset'), $regexMasterSrcset);
            $noscript = preg_replace_callback($regex,
                array($this, 'replace_custom_srcset'),
                $noscript
            );
            $this->logger->log("NOSCRIPT SRCSET: $noscript");
            $content = str_replace("<noscript>__sp_noscript_plAc3h0ldR_$i</noscript>", $noscript, $content);
        }

        for ($i = 0; $i < count($this->scripts); $i++) {
            $this->logger->log("SCRIPT $i");
            $script = $this->scripts[$i];
            if($this->ctrl->settings['parse_json']) {
                $script = preg_replace_callback('/(\<script[^>]*(?:\btype=(?:"|\')application\/(?:ld\+|)json(?:"|\'))[^>]*\>)(.*)\<\/script\>/sU',
                    array($this, 'replace_application_json_script'),
                    $script
                );
            }
            $content = str_replace("<script>__sp_script_plAc3h0ldR_$i</script>", $script, $content);
        }
        for ($i = 0; $i < count($this->CDATAs); $i++) {
            $content = str_replace("<![CDATA[\n__sp_cdata_plAc3h0ldR_$i\n]]>", $this->CDATAs[$i], $content);
        }

        //$content = str_replace('{{SPAI-AFFECTED-TAGS}}', implode(',', array_keys($this->ctrl->affectedTags)), $content);
        unset($this->ctrl->affectedTags['img']);
        if(strpos($content, '{{SPAI-AFFECTED-TAGS}}')) {
            $content = str_replace('{{SPAI-AFFECTED-TAGS}}', addslashes(json_encode($this->ctrl->affectedTags)), $content);
        } else {
            $content = str_replace('</body>', '<script>var spai_affectedTags = "' . addslashes(json_encode($this->ctrl->affectedTags)) . '";</script></body>', $content);
        }

        $this->logger->log("******** REGEX PARSER RETURN *********");

        return $content;
    }

    public function replace_crowd2_img_styles($matches) {
        $text = $matches[0];
        $style = $matches[4];
        if(strpos($style, '--img-') === false) return $text;
        $qm = strlen($matches[3]) ? $matches[3] : '"';

        $pattern = $matches[2] . '=' . $matches[3] . $matches[4] . $matches[3];
        $pos = strpos($text, $pattern);
        if($pos === false) return $text;

        $replacedStyle = $this->cssParser->replace_crowd2_img_styles($matches[4]);
        $replacement = ' ' . $matches[2] . '=' . $qm . $replacedStyle . $qm;

        $this->logger->log("CROWD2 - style found, string $pattern is replaced by $replacement");

        $str = substr($text, 0, $pos) . $replacement . substr($text, $pos + strlen($pattern));
        return $str;
    }

        /**
     * extract the specific block into the store array. Preferred over the out-of-the box preg_replace_callback because as the content of the blocks must be un-greedy, in many cases when the blocks are large
     * a "catastrophic backtrace" exception is thrown by the PHP function.
     * @param $content
     * @param $id - code for the block - will be put in the replacement
     * @param $startMarker eg. <![CDATA[ or <noscript>. If the last character is '.' then the string without it will be searched but replaced with the string ending in '>' instead of '.' ( search for <script and replace with <script>)
     * @param $endMarker eg ]]> or </noscript>
     * @param $store - by ref. the array in which the extracted blocks will be put
     * @param bool $newLine if true it adds a new line after/before the start/end markers, needed for CDATA
     * @return string - the changed $content
     */
    public function extract_blocks($content, $id, $startMarker, $endMarker, &$store, $newLine = false)
    {
        $matches = array();
        $startMarkerRepl = $startMarker;
        if(substr($startMarker, -1) == '.') {
            $startMarker = substr($startMarker, 0, -1);
            $startMarkerRepl = $startMarker . '>';
        }
        $startMarkerLen = strlen($startMarker);
        $endMarkerLen = strlen($endMarker);
        for($idx = 0, $match = false, $len = strlen($content); $idx < $len - $endMarkerLen + 1; $idx++) {
            if($match) {
                if(substr( $content, $idx, $endMarkerLen) == $endMarker) {
                    //end of CDATA block
                    $matches[] = (object)array('start' => $match ? $match : 0, 'end' => $idx + $endMarkerLen - 1);
                    $idx += $endMarkerLen - 1;
                    $match = false;
                }
            } else {
                if(substr($content, $idx, $startMarkerLen) == $startMarker) {
                    $match = $idx;
                    $idx += $startMarkerLen - 1;
                }
            }
        }
        $this->logger->log(" MATCHED $startMarker BLOCKS: " . json_encode($matches));
        $replacedContent = '';
        $nl = ($newLine ? "\n" : '');
        for($idx = 0; $idx < count($matches); $idx++) {
            $start = isset($matches[$idx - 1]) ? $matches[$idx - 1]->end + 1 : 0;
            $replacedContent .= substr($content, $start, $matches[$idx]->start - $start) . $startMarkerRepl . $nl . $id . '_plAc3h0ldR_' .$idx . $nl . $endMarker;
            $cdata = substr($content, $matches[$idx]->start, $matches[$idx]->end - $matches[$idx]->start + 1);
            $this->logger->log(" MATCHED AND EXTRACTED: " . $cdata);
            $store[] = $cdata;
        }
        $replacedContent .= substr($content, isset($matches[$idx - 1]) ? $matches[$idx - 1]->end + 1 : 0);
        return $replacedContent;
    }

    public function replace_scripts($matches)
    {
        $index = count($this->scripts);
        $this->scripts[] = $matches[0];
        return "<script>__sp_script_plAc3h0ldR_$index</script>";
    }

    public function replace_styles($matches)
    {
        //$this->logger->log("STYLE: " . $matches[0]);
        $index = count($this->styles);
        $this->styles[] = $matches[0];
        return "<style>__sp_style_plAc3h0ldR_$index</style>";
    }

    public function replace_images($matches)
    {
        if (count($matches) < 5 || strpos($matches[0], $matches[2] . '=' . $matches[3] . 'data:image/svg+xml;' . ($this->extMeta ? 'base64' : 'u='))) {
            //avoid duplicated replaces due to filters interference
            return $matches[0];
        }
        if ($this->classFilter && !preg_match('/\bclass=(\"|\').*?\b' . $this->classFilter . '\b.*?(\"|\')/s', $matches[0])) {
            return $matches[0];
        }

        if ($this->attrFilter) {
            if($this->attrValFilter) {
                if (!preg_match('/\b' . $this->attrFilter . '=((\"|\')[^\"|\']*\b|)' . preg_quote($this->attrValFilter, '/') . '/s', $matches[0])) {
                    return $matches[0];
                }
            } else {
                $stripped = preg_replace('/(\"|\').*?(\"|\')/s', ' ', $matches[0]); //keep only the attribute's names
                if (!preg_match('/\b' . $this->attrFilter . '=/s', $stripped)) {
                    return $matches[0];
                }
            }
        }
        //$matches[2] will be either " or '
        return $this->_replace_images($matches[1], $matches[2], $matches[0], isset($matches[5]) ? $matches[5] : trim($matches[4]), $matches[3]);
    }

    protected function _replace_images($tag, $attr, $text, $url, $q = '') {
        $this->logger->log("******** REPLACE IMAGE IN $tag ATTRIBUTE $attr: " . $url . ($this->extMeta ? " EXT" : " INT"));

        $extMeta = $this->extMeta;
        if($this->ctrl->urlIsApi($url)) {$this->logger->log('IS API');return $text;}
        if(!ShortPixelUrlTools::isValid($url)) {$this->logger->log('NOT VALID');return $text;}
        if($this->ctrl->urlIsExcluded($url)) {$this->logger->log('EXCLUDED');return $text;}

        //custom exclusion for SliderRevolution TODO unhack
        $integrations = $this->ctrl->getActiveIntegrations();
        if($integrations['slider-revolution'] && preg_match('/plugins\/revslider\/.*\/dummy.png$/', $url )) {
            return $text;
        }

        $pristineUrl = $url;
        //WP is encoding some characters, like & ( to &#038; )
        $url = html_entity_decode($url);

        if(   !$this->ctrl->lazyNoticeThrown && substr($url, 0,  10) == 'data:image'
            && (   strpos($text, 'data-lazy-src=') !== false
                || strpos($text, 'data-layzr=') !== false
                || strpos($text, 'data-src=') !== false
                || (strpos($text, 'data-orig-src=') !== false && strpos($text, 'lazyload')) //found for Avada theme with Fusion Builder
            )) {
            set_transient("shortpixelai_thrown_notice", array('when' => 'lazy', 'extra' => false), 86400);
            $this->ctrl->lazyNoticeThrown = true;
        }
        if($this->ctrl->lazyNoticeThrown) {
            $this->logger->log("Lazy notice thrown");
            return $text;
        }
        //early check for the excluded selectors - only the basic cases when the selector is img.class
        if($this->ctrl->tagIs('excluded', $text)) {
            $this->logger->log("Excluding: " . $text);
            return $text;
        }
        //prevent cases when html code including data-spai attributes gets copied into new articles
        if(strpos($text, 'data-spai') > 0) {
            if(strpos($text, 'data:image/svg+xml;' . ($extMeta ? 'base64' : 'u=')) > 0) {
                //for cases when the src is pseudo
                //Seems that Thrive Architect is doing something like this under the hood? (see https://secure.helpscout.net/conversation/862862953/16430/)
                return $text;
            }
            //for cases when it's normal URL, just get rid of data-spai's
            $text = preg_replace('/data-spai(-upd|)=["\'][0-9]*["\']/s', '', $text);
        }

        $noresize = $this->ctrl->tagIs('noresize', $text) || $this->ctrl->tagIs('eager', $text);

        $this->logger->log("Including: " . $url);

        //some particular cases are hardcoded here...
        // 1. Revolution Slider (rev-slidebg) Glow Pro's Swiper slider (attachment-glow-featured-slider-img) and Optimizer PRO's frontpage slider (stat_bg_img)
        if(strpos($text,'attachment-glow-featured-slider-img')) {
            $this->ctrl->affectedTags['figure'] = 1; //the Glow Pro moves the image in <figure> tags
        }
        //    both discard the data-spai-src-meta :( so we will put the original URL inside the src
        if($extMeta && $tag == 'img' && preg_match('/\bclass=(\"|\').*?\b(rev-slidebg|stat_bg_img|attachment-glow-featured-slider-img)\b.*?(\"|\')/s', $text)) {
            $extMeta = false;
        }

        //Get current image size
        $sizes = ShortPixelUrlTools::get_image_size($url);
        $this->logger->log('Got Sizes: ', $sizes);
        $qex = strlen($q) ? '' : '"';
        $qm = strlen($q) ? $q : '"';


        if($this->attrToIntegrateAndRemove && isset($sizes['thumb'])) { //sizes[3] means that it's a thumbnail, judging from the file name
            $this->logger->log('Integrate and remove: ' . $this->attrToIntegrateAndRemove . '. Sizes: ', $sizes);
            $matches = false;
            $this->logger->log('TEXT: ' . $text);
            if(preg_match('/\b' . $this->attrToIntegrateAndRemove . '=(\"|\')(.*?)(?:\"|\')/s', $text, $matches) && isset($matches[2])){
                $this->logger->log('Matched:', $matches);
                $items = explode(',', $matches[2]);
                foreach($items as $item) {
                    $parts = explode(' ', trim($item));
                    $partSizes = ShortPixelUrlTools::get_image_size($parts[0]);
                    if(isset($partSizes[0]) && $partSizes[0] > $sizes[0]) {
                        $sizes = $partSizes;
                        $url = $parts[0];
                    }
                }
            }
        }

        $spaiMeta = ''; $spaiMarker = ' data-spai=' . $qm . '1' . $qm;
        if($noresize || $this->isEager) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $inlinePlaceholder = $this->ctrl->get_api_url(false) . ($ext == 'css' ? '+v_' . $this->ctrl->cssCacheVer : '') . '/' . ShortPixelUrlTools::absoluteUrl($url);
            $spaiMarker = ' data-spai-eager=' . $qm . '1' . $qm;
        } else {
            if($extMeta){
                $data = isset($sizes[0]) ? ShortPixelUrlTools::generate_placeholder_svg_pair($sizes[0], $sizes[1], /*$this->absoluteUrl(*/$url) : ShortPixelUrlTools::generate_placeholder_svg_pair(false, false, $url);
                $inlinePlaceholder = $data->image;
                $spaiMeta = $data->meta ? ' data-spai-' . $attr . '-meta="' . $data->meta . '"' : '';
            } else {
                $inlinePlaceholder = isset($sizes[0]) ? ShortPixelUrlTools::generate_placeholder_svg($sizes[0], $sizes[1], /*$this->absoluteUrl(*/$url) : ShortPixelUrlTools::generate_placeholder_svg(false, false, $url);
            }
        }
        $pattern = '/\s' . $attr . '=' . preg_quote($q . $pristineUrl . $q, '/') . '/';
        $replacement = ' '. $attr . '=' . $qm . $inlinePlaceholder . $qm . $spaiMarker . $spaiMeta;
        $str = preg_replace($pattern, $replacement, $text);
        if($this->attrToIntegrateAndRemove) {
            $str = preg_replace('/' . $this->attrToIntegrateAndRemove . '=(\"|\').*?(\"|\')/s',' ', $str);
        }
        $this->ctrl->affectedTags[$tag] = 1 | (isset($this->ctrl->affectedTags[$tag]) ? $this->ctrl->affectedTags[$tag] : 0);
        //$this->logger->log("Replaced pattern: $pattern with $replacement. RESULTED TAG: $str");
        return $str;// . "<!-- original url: $url -->";
    }

    public  function replace_wc_gallery_thumbs( $matches ) {
        $url = ShortPixelUrlTools::absoluteUrl($matches[1]);
        $str = str_replace($matches[1], ShortPixelUrlTools::generate_placeholder_svg(1000, 1000, $url) , $matches[0]);
        if($str != $matches[0]) {
            $this->ctrl->affectedTags['div'] = 1 | (isset($this->ctrl->affectedTags['div']) ? $this->ctrl->affectedTags['div'] : 0);
        }
        return $str;
    }

    /**
     * for data-envira-srcset currently
     * @param $matches
     * @return null|string|string[]
     */
    public function replace_custom_srcset($matches)
    {
        $qm = strlen($matches[3]) ? $matches[3] : '"';
        $text = $matches[0];
        $pattern = $matches[2] . '=' . $matches[3] . $matches[4] . $matches[3];
        $replacement = ' ' . $matches[2] . '=' . $qm . $this->replace_srcset($matches[4]) . $qm;
        $pos = strpos($text, $pattern);
        if($pos === false) return $text;
        $str = substr($text, 0, $pos) . $replacement . substr($text, $pos + strlen($pattern));
        return $str;// . "<!-- original url: $url -->";
    }

    /**
     * parses a <script type="application/json">
     * @param $matches
     */
    public function replace_application_json_script($matches) {
        $jsonParser = new ShortPixelJsonParser($this->ctrl);
        $dataObj = json_decode(str_replace('&quot;', '"', $matches[2]));
        if(json_last_error() === JSON_ERROR_SYNTAX) {
            $this->logger->log("APPLICATION JSON syntax error", $matches);
            return $matches[0];
        }
        $this->logger->log("APPLICATION / JSON", $dataObj);
        return $matches[1] . json_encode($jsonParser->parse($dataObj)) . '</script>';
    }

    /**
     * for data-envira-srcset currently
     * @param $matches
     * @return null|string|string[]
     */
    public function replace_custom_json_attr($matches)
    {
        $qm = strlen($matches[3]) ? $matches[3] : '"';
        $text = $matches[0];
        $tag = $matches[1];
        $attr = $matches[2];
        $jsonParser = new ShortPixelJsonParser($this->ctrl);
        $parsed = json_decode(str_replace('&quot;', '"', $matches[4]));
        if(json_last_error() === JSON_ERROR_SYNTAX) return $text;
        $replaced = str_replace('"', '&quot;',json_encode($jsonParser->parse($parsed), JSON_UNESCAPED_SLASHES));
        if($matches[4] == $replaced) return $text;
        $pattern = $attr . '=' . $matches[3] . $matches[4] . $matches[3];
        $replacement = ' ' . $attr . '=' . $qm . $replaced . $qm . ' data-spai-eager='. $qm . '1' . $qm;
        $pos = strpos($text, $pattern);
        if($pos === false) return $text;
        $str = substr($text, 0, $pos) . $replacement . substr($text, $pos + strlen($pattern));
        if(strtolower($tag) == 'section') {
            $this->ctrl->affectedTags['div'] = 3;
        }
        return $str;// . "<!-- original url: $url -->";
    }

    /**
     * for data-product_variations
     * @param $matches
     * @return null|string|string[]
     */
    public function replace_product_variations($matches)
    {
        $this->logger->log("PRODUCT VARIATION", $matches);
        $qm = strlen($matches[3]) ? $matches[3] : '"';
        $parsed = json_decode(str_replace('&quot;', '"', $matches[4]));
        $text = $matches[0];
        if(($err = json_last_error()) === JSON_ERROR_SYNTAX) {
            $this->logger->log("VARIATIONS - JSON PARSE ERROR " . $err);
            return $text;
        }
        if(!is_array($parsed)) {
            return $text;
        }
        $this->logger->log('VARIATIONS - parsed');
        for($i = 0; $i < count($parsed); $i++) {
            if(isset($parsed[$i]->image->srcset)) {
                $this->logger->log("VARIATIONS - srcset " . $parsed[$i]->image->srcset);
                $parsed[$i]->image->srcset = preg_replace_callback('/data:image\/svg\+xml;u=[^\s]*/s',
                    array($this, 'pseudo_url_to_api_url'),
                    $parsed[$i]->image->srcset
                );
            }
        }
        $replaced = str_replace('"', '&quot;',json_encode($parsed, JSON_UNESCAPED_SLASHES));
        if($matches[4] == $replaced) return $text;
        $pattern = $matches[2] . '=' . $matches[3] . $matches[4] . $matches[3];
        $replacement = $matches[2] . '=' . $qm . $replaced . $qm . ' data-spai-eager=' . $qm . '1' . $qm;
        $pos = strpos($text, $pattern);
        if($pos === false) return $text;
        $str = substr($text, 0, $pos) . $replacement . substr($text, $pos + strlen($pattern));
        return $str;// . "<!-- original url: $url -->";
    }

    public function pseudo_url_to_api_url($match){
        $this->logger->log("VARIATIONS SRCSET ITEM", $match);
        $url = ShortPixelUrlTools::url_from_placeholder_svg($match[0]);
        $this->logger->log("VARIATIONS SRCSET URL", $url);
        return $this->ctrl->get_api_url(false) . '/' . ShortPixelUrlTools::absoluteUrl($url);
    }

    public function replace_srcset($srcset) {
        $aiSrcsetItems = array();
        $aiUrl = $this->ctrl->get_api_url(false);
        $aiUrlBase = $this->ctrl->settings['api_url'];
        $srcsetItems = explode(',', $srcset);
        foreach($srcsetItems as $srcsetItem) {
            $srcsetItem = trim($srcsetItem);
            $srcsetItemParts = explode(' ', $srcsetItem);
            if($this->ctrl->urlIsExcluded($srcsetItemParts[0])) {
                //if any of the items are excluded, don't replace
                return $srcset;
            }
            if(strpos($srcsetItem, $aiUrlBase) !== false || strpos($srcsetItem, 'data:image/') === 0) {
                return $srcset; //already parsed by the hook.
            }
            $prefix = strpos($aiUrl, 'http') === 0 ? '' : 'http:';
            $aiSrcsetItems[] = $prefix . $aiUrl . '/' . ShortPixelUrlTools::absoluteUrl(trim($srcsetItem));
        }
        return implode(', ', $aiSrcsetItems);
    }

    //NextGen specific
    //TODO make gallery specific
    public function replace_link_href($matches)
    {
        if (count($matches) < 3 || strpos($matches[0], 'href=' . $matches[1] . 'data:image/svg+xml;u=')
            || strpos($matches[0], 'ngg-fancybox') === false) { //this is to limit replacing the href to NextGen's fancybox links
            //avoid duplicated replaces due to filters interference
            return $matches[0];
        }
        //$matches[1] will be either " or '
        return $this->_replace_images('a', 'href', $matches[0], $matches[2], $matches[1]);
    }

    public static function parseInlineStyle() {

    }
}