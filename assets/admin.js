/* global CPCS, jQuery */
(function ($) {
    'use strict';

    function showMsg($el, text, type) {
        $el.text(text).removeClass('success error').addClass(type).show();
    }
    function spin($s, $b, on) {
        on ? ($s.css('display','inline-block'), $b.prop('disabled',true))
           : ($s.hide(), $b.prop('disabled',false));
    }

    // ── Auto-fetch CSS ────────────────────────────────────────────────────────
    $('#cpcs-fetch-css').on('click', function () {
        var url = $('#cpcs-page-url').val().trim();
        if (!url) { alert('Enter a page URL first.'); return; }
        var $b = $(this).text(CPCS.strings.fetching).prop('disabled',true);
        $.post(CPCS.ajaxurl, { action:'cpcs_fetch_css', nonce:CPCS.nonce, page_url:url })
         .done(function(r){ r.success ? $('#cpcs-full-css').val(r.data.css) : alert('Error: '+r.data); })
         .fail(function(){ alert('Network error.'); })
         .always(function(){ $b.text('Auto-Fetch CSS').prop('disabled',false); });
    });

    // ── Generate ──────────────────────────────────────────────────────────────
    $('#cpcs-generate').on('click', function () {
        var url = $('#cpcs-page-url').val().trim();
        if (!url) { alert('Enter a page URL.'); return; }
        var $b = $('#cpcs-generate'), $s = $('#cpcs-spinner');
        spin($s,$b,true); $b.text(CPCS.strings.generating);
        $('#cpcs-result-wrap').hide(); $('#cpcs-status-msg').hide();
        $.post(CPCS.ajaxurl, { action:'cpcs_generate', nonce:CPCS.nonce, page_url:url, full_css:$('#cpcs-full-css').val() })
         .done(function(r){
             spin($s,$b,false); $b.text('Generate Critical CSS');
             if (r.success) { $('#cpcs-output').val(r.data.critical_css); $('#cpcs-result-wrap').show(); }
             else alert('Error: '+r.data);
         })
         .fail(function(){ spin($s,$b,false); $b.text('Generate Critical CSS'); alert('Network error.'); });
    });

    // ── Copy ──────────────────────────────────────────────────────────────────
    $('#cpcs-copy-btn').on('click', function () {
        $('#cpcs-output').select(); document.execCommand('copy');
        $(this).text('Copied!'); setTimeout(()=>$(this).text('Copy'),1500);
    });

    // ── Save generated ────────────────────────────────────────────────────────
    $('#cpcs-save-btn').on('click', function () {
        var url=$('#cpcs-page-url').val().trim(), css=$('#cpcs-output').val().trim();
        var $msg=$('#cpcs-status-msg'), $b=$(this).prop('disabled',true);
        if (!url||!css){ showMsg($msg,'URL and CSS required.','error'); $b.prop('disabled',false); return; }
        $.post(CPCS.ajaxurl,{action:'cpcs_save',nonce:CPCS.nonce,page_url:url,critical_css:css})
         .done(function(r){
             $b.prop('disabled',false);
             r.success ? (showMsg($msg,CPCS.strings.success,'success'), setTimeout(()=>location.reload(),1200))
                       : showMsg($msg,'Error: '+r.data,'error');
         }).fail(function(){ $b.prop('disabled',false); showMsg($msg,CPCS.strings.error,'error'); });
    });

    // ── Manual save ───────────────────────────────────────────────────────────
    $('#cpcs-manual-save').on('click', function () {
        var url=$('#cpcs-manual-url').val().trim(), css=$('#cpcs-manual-css').val().trim();
        var $msg=$('#cpcs-manual-msg'), $s=$('#cpcs-manual-spinner'), $b=$(this);
        if (!url||!css){ showMsg($msg,'Both fields required.','error'); return; }
        spin($s,$b,true);
        $.post(CPCS.ajaxurl,{action:'cpcs_save',nonce:CPCS.nonce,page_url:url,critical_css:css})
         .done(function(r){
             spin($s,$('#cpcs-manual-save'),false);
             r.success ? (showMsg($msg,CPCS.strings.success,'success'), setTimeout(()=>location.reload(),1200))
                       : showMsg($msg,'Error: '+r.data,'error');
         }).fail(function(){ spin($s,$('#cpcs-manual-save'),false); showMsg($msg,CPCS.strings.error,'error'); });
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    $(document).on('click','.cpcs-delete-btn',function(){
        if (!confirm(CPCS.strings.confirm_delete)) return;
        var id=$(this).data('id');
        $.post(CPCS.ajaxurl,{action:'cpcs_delete',nonce:CPCS.nonce,entry_id:id})
         .done(function(r){ r.success && $('#cpcs-row-'+id).fadeOut(300,function(){$(this).remove();}); });
    });

    // ── View/Edit modal ───────────────────────────────────────────────────────
    $(document).on('click','.cpcs-view-btn',function(){
        var $b=$(this);
        $('#cpcs-modal-id').val($b.data('id'));
        $('#cpcs-modal-url').val($b.data('url'));
        $('#cpcs-modal-css').val($b.data('css'));
        $('#cpcs-modal-msg').hide();
        $('#cpcs-modal-overlay').show();
    });
    $('#cpcs-modal-close, #cpcs-modal-overlay').on('click',function(e){
        if(e.target===this) $('#cpcs-modal-overlay').hide();
    });
    $('#cpcs-modal-save').on('click',function(){
        var id=$('#cpcs-modal-id').val(), url=$('#cpcs-modal-url').val().trim(), css=$('#cpcs-modal-css').val().trim();
        var $msg=$('#cpcs-modal-msg'), $s=$('#cpcs-modal-spinner'), $b=$(this);
        if (!url||!css){ showMsg($msg,'Both fields required.','error'); return; }
        spin($s,$b,true);
        $.post(CPCS.ajaxurl,{action:'cpcs_save',nonce:CPCS.nonce,entry_id:id,page_url:url,critical_css:css})
         .done(function(r){
             spin($s,$('#cpcs-modal-save'),false);
             r.success ? (showMsg($msg,CPCS.strings.success,'success'), setTimeout(()=>location.reload(),1000))
                       : showMsg($msg,'Error: '+r.data,'error');
         }).fail(function(){ spin($s,$('#cpcs-modal-save'),false); showMsg($msg,CPCS.strings.error,'error'); });
    });

    // ── Purge font cache ──────────────────────────────────────────────────────
    $('#cpcs-purge-fonts').on('click',function(){
        var $b=$(this).text(CPCS.strings.purging).prop('disabled',true);
        var $msg=$('#cpcs-purge-fonts-msg');
        $.post(CPCS.ajaxurl,{action:'cpcs_purge_fonts',nonce:CPCS.nonce})
         .done(function(r){
             $b.text('Purge Font Cache').prop('disabled',false);
             showMsg($msg, r.success ? CPCS.strings.purged : 'Error.', r.success?'success':'error');
         }).fail(function(){ $b.text('Purge Font Cache').prop('disabled',false); showMsg($msg,'Network error.','error'); });
    });

})(jQuery);
