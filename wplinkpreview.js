(function() {
	tinymce.PluginManager.add( 'wplinkpreview_plugin', function( editor, url ) {
		editor.addButton('wplinkpreview_plugin', {
			title: 'Insert Link Preview',
            icon: 'mce-ico mce-i-preview wplinkpreview',
            onclick: function() {
                editor.windowManager.open({
                    title: 'WP Link Preview',
                    body: [{
                        type: 'textbox',
                        name: 'url',
                        label: 'URL'
                    }],
                    onsubmit: function( e ) {
                        jQuery.ajax({
                            type: 'GET',
                            url: ajaxurl,
                            data: {
                                action: 'fetch_wplinkpreview',
                                url: e.data.url
                            },
                            success: function(html) {
                                editor.insertContent(html);
                            }
                        });                    
                    }
                });
            }
		});
	});
})();