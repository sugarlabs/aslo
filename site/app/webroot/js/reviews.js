// Cheap JS detect for CSS - if it executes, add a class to the body 
// indicating JS works.
$('body').addClass('with-js');

$(document).ready(function() {

    // Wire up a top-level event delegation handler for all drop-down 
    // selectors in the main content area.  This prevents the need to wire
    // every single selector on page, including those loaded by AJAX.
    $('#content-main').change(function(e) {

        // Only handle as a review flag selector if parent has 'reason' class.
        if ( $(e.target.parentNode).hasClass('reason') ) {
        
            // Grab the selection field, blur because it looks better.
            var reason_sel = e.target;
            reason_sel.blur();

            // Flag the review if there's a non-blank reason selected.
            var reason_opt = reason_sel.options[reason_sel.selectedIndex];
            var reason = reason_opt.value;
            if (reason) {
                var the_form = $(reason_sel).parents('form');

                // If the reason is 'other', prompt for the reason notes.
                if (reason == 'review_flag_reason_other') {

                    // HACK: To cop out on translation worries, just scoop up 
                    // elements from the hidden form.
                    var select_title = reason_opt.innerHTML;
                    var notes_label  = the_form
                        .children('label.FlagNotesLabel').text();

                    // TODO: Maybe make this fancy and DHTML-y someday.
                    var reason_notes = '';
                    reason_notes = window.prompt(select_title, reason_notes);

                    // Ask for notes to go along with the choice of "other"
                    if (reason_notes === null) {
                        // Bail out! Looks like the cancel button was hit!
                        reason_sel.selectedIndex = 0; return;
                    }

                    // Populate the hidden form field with the notes.
                    the_form.children('input.FlagNotes')
                        .attr('value', reason_notes);
                }

                // Start the spinner and launch the AJAX request.
                the_form.children('.reason').addClass('loading');
                var review_id = the_form
                    .children('input.ReviewId').attr('value');
                $.ajax({
                    type : 'POST',
                    url : flagurl + '/' + review_id + '/ajax',
                    data : the_form.serialize(),
                    success : function(t) {
                        $('#flag-' + review_id).hide()
                            .html(t)
                            .fadeIn('slow');
                    },
                    error : function(req) {
                        the_form.children('.reason').removeClass('loading');
                        the_form.children('.reason').children('.error').hide()
                            .html(req.responseText).fadeIn('slow');
                        reason_sel.selectedIndex = 0;
                    }
                });
            }

        }

    });

    // Wire up all the links to reveal older reviews with Ajax in-place loaders.
    $('.review .others-by-user a').click(function(e) {

        // Convert from the reveal link parent ID to the content load div ID.
        var t_pos = 0, t_id = '#' + this.parentNode.id;
        if (t_pos = t_id.lastIndexOf('-')) {
            var load_id = '#others-by-user-load-' + t_id.substring(t_pos+1);

            // Hide the reveal link and content div in preparation for reveal.
            $(this).blur();
            $(this).addClass('loading');
            $(load_id).slideUp('fast');

            // Load up the HTML for the user's other hidden reviews.
            $(load_id).load(
                this.href + '&bare=1&skip_first=1', 
                null, 
                function(data, status, req) {
                    // Hide the link and reveal the just-loaded content.
                    $(t_id).fadeOut('fast');
                    $(load_id).slideDown('slow');
                    return true;
                }
            )

            return false;
        }

    });

});
