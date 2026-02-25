jQuery(document).ready(function($) {
    var $wp_inline_edit = inlineEditPost.edit;
    inlineEditPost.edit = function( id ) {
        // Call the original function first.
        $wp_inline_edit.apply( this, arguments );
        
        // Get the post ID.
        var postId = 0;
        if ( typeof(id) === 'object' ) {
            postId = parseInt( this.getId( id ) );
        }
        if ( postId > 0 ) {
            var $edit_row = $('#edit-' + postId);
            var $post_row = $('#post-' + postId);
            
            // Populate the Buchstabe field.
            var buchstabe = $post_row.find('.column-buchstabe').text().trim();
            $edit_row.find('input[name="lexikon_buchstabe"]').val(buchstabe);
            
            // Populate the Insolvenz Typ checkboxes.
            var tabsData = $post_row.find('.lexikon_tabs_data').text().trim();
            // First, clear all checkboxes.
            $edit_row.find('input[name="lexikon_tabs[]"]').prop('checked', false);
            if (tabsData) {
                var tabArray = tabsData.split(',');
                $.each(tabArray, function(index, value) {
                    $edit_row.find('input[name="lexikon_tabs[]"][value="'+value+'"]').prop('checked', true);
                });
            }
        }
    };
});
