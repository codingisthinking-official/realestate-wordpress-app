/**
 * Created by simon on 02.07.2018.
 */

function SPAIAdmin(){};

SPAIAdmin.prototype.adjustSettingsTabsHeight = function(){
    var sectionHeight = jQuery('.wp-shortpixel-ai-options section#tab-settings table').height() + 80;
    sectionHeight = Math.max(sectionHeight, jQuery('.wp-shortpixel-ai-options section#tab-adv-settings table').height() + 80);
    sectionHeight = Math.max(sectionHeight, jQuery('section#tab-resources .area1').height() + 60);
    jQuery('#shortpixel-ai-settings-tabs').css('height', sectionHeight);
    jQuery('#shortpixel-ai-settings-tabs section').css('height', sectionHeight);
}

SPAIAdmin.prototype.switchSettingsTab = function(target){
    var tab = target.replace("tab-",""),
        section = jQuery("section#" +target);

    jQuery('input[name="section_name"]').val(tab);
    var uri = window.location.href.toString();
    if (uri.indexOf("?") > 0) {
        var clean_uri = uri.substring(0, uri.indexOf("?"));
        clean_uri += '?' + jQuery.param({'page':'shortpixel_ai_settings_page', 'section': tab});
        window.history.replaceState({}, document.title, clean_uri);
        //window.location.href = clean_uri;
    }

    if(section.length > 0){
        jQuery("section").removeClass("sel-tab");
        jQuery("section#" +target).addClass("sel-tab");
    }
}

SPAIAdmin.prototype.dismissNotice = function(url, id, action) {
    jQuery("#short-pixel-ai-notice-" + id).hide();
    var data = { action  : 'shortpixel_ai_dismiss_notice',
        notice_id: id,
        call: action};
    jQuery.get(url, data, function(response) {
        data = JSON.parse(response);
        if(data["Status"] == 'success') {
            console.log("dismissed");
            jQuery("#short-pixel-ai-success-" + id).show();
        }
        else {
            jQuery("#short-pixel-ai-notice-" + id).show();
            if(typeof data['Details'] !== undefined && data['Details']['Status'] == -3) {
                jQuery("#short-pixel-ai-notice-" + id + " .spai-action-error").text(data['Details']['Message']);
            }
            jQuery("#short-pixel-ai-notice-" + id + " .spai-action-error").css('display', 'inline-block');
        }
    });
}

SPAIAdmin.prototype.clearCssCache = function(url, evt) {
    evt.stopPropagation();

    evt.target.setAttribute('disabled','true');
    jQuery(evt.target).addClass('button').removeClass('button-primary');
    evt.target.innerText = 'Clearing the cache...';
    var data = { 'action': 'shortpixel_ai_clear_css_cache'};
    jQuery.get(url, data, function(response) {
        evt.target.innerText = '. Cache  cleared .';
    });
}


window.ShortPixelAIAdmin = new SPAIAdmin();

