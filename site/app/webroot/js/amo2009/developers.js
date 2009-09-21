jQuery(document).ready(function($){

    /* Hijack 'I like this!' forms. */
    $('.like-this form').submit(function(e){
        e.preventDefault();

        var the_form = $(this);
        parent = the_form.parent('.like-this').fadeOut('fast', function() {
            $(this).toggleClass('not-liked').toggleClass('liked').fadeIn('fast');
        });

        /* We should care more about the return, oh well. */
        $.post(this.action, the_form.serialize());
    });

    /* Show/hide versions if the application is checked. */
    $('input[name^=applications]').change(function(e){
        $(this).closest('.applications')
            .find('li[name=' + this.value + ']').toggle(this.checked);
    }).change();
});
