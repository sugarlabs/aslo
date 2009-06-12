var collections = {};
/**
 * These members need to be set on the collections object:
 *   subscribe_url
 *   unsubscribe_url
 *   adding_text
 *   removing_text
 *   add_text
 *   remove_text
 *
 * Optional:
 *   adding_img
 *   removing_img
 *   remove_img
 *   add_img
 */

collections.init = function(){

    var sum = function(arr) {
        var ret = 0;
        $.each(arr, function(_, i) { ret += i; });
        return ret;
    };

    /* We don't want the button to shrink when the contents
     * inside change. */
    var maintain_width = function(b) {
        var w = sum($.map(['width', 'padding-left', 'padding-right',
                           'border-left-width', 'border-right-width'],
                          function(w){ return parseFloat(b.css(w)); })
                   );
        b.css('min-width', w);
    };

    var modal = function(content) {
        if ($.cookie('collections-leave-me-alone'))
            return;

        var e = $('<div class="modal-subscription">' + content + '</div>');
        e.appendTo(document.body).jqm().jqmAddClose('a.close-button').jqmShow();
        e.find('#bothersome').change(function(){
            // Leave me alone for 1 year (doesn't handle leap years).
            $.cookie('collections-leave-me-alone', true,
                     {expires: 365, path: c.cookie_path});
            e.jqmHide();
        });
    };

    var c = collections;

    /* Hijack form.favourite for some ajax fun. */
    $('form.favourite').submit(function(event){
        event.preventDefault();

        // `this` is the form.
        var fav_button = $(this).find('button');
        var previous = fav_button.html();
        var is_fav = fav_button.hasClass('fav');

        /* Kind should be in ['adding', 'removing', 'add', 'remove'] */
        var button = function(kind) {
            var text = c[kind + '_text'];
            /* The listing page doesn't have an inline image, detail page does. */
            if (fav_button.find('img').length) {
                var img = c[kind + '_img'];
                fav_button.html('<img src="' + img + '"/>' + text);
            } else {
                fav_button.html(text);
            }
        };

        maintain_width(fav_button);
        fav_button.addClass('loading-fav').attr('disabled', 'disabled');
        button(is_fav ? 'removing' : 'adding');
        maintain_width(fav_button);

        $.ajax({
            type: "POST",
            data: $(this).serialize(),
            url: is_fav ? c.unsubscribe_url : c.subscribe_url,
            success: function(content){
                if (is_fav) {
                    fav_button.removeClass('fav');
                    button('add');
                } else{
                    modal(content);
                    fav_button.addClass('fav');
                    button('remove');
                }
                // Holla back at the extension.
                bandwagonRefreshEvent();
            },
            error: function(){
                fav_button.html(previous);
            },
            complete: function(){
                fav_button.attr('disabled', '');
                fav_button.removeClass('loading-fav');
            }
        });
    });
};
