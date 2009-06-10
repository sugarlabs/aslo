jQuery(function($) {
	// A special slideshow that updates the teaser 'selected' list item
	function HeaderSlideshow() {
		Slideshow.call(this);
	}
	HeaderSlideshow.prototype = new Slideshow();
	HeaderSlideshow.prototype.moveToItem = function(itemNumber) {
		Slideshow.prototype.moveToItem.call(this, itemNumber);
		$('.section-teaser .teaser-header li').removeClass('selected');
		$('.section-teaser .teaser-header li').eq(itemNumber - 1).addClass('selected');
	};
	
	var homepageSlider = new HeaderSlideshow();
	homepageSlider.itemContainer = '.teaser-items';
	homepageSlider.wrapperElement = 'div';
	homepageSlider.wrapperClass = 'window';
	homepageSlider.controlsMarkup = (
		'<p class="slideshow-controls">' + 
		'<a href="#" class="prev" rel="prev">Previous</a>' + 
		'<a href="#" class="next" rel="next">Next</a></p>'
	);
	homepageSlider.leftController = '.section-teaser a[rel="prev"]';
	homepageSlider.rightController = '.section-teaser a[rel="next"]';
	homepageSlider.activeClass = 'active';
	homepageSlider.container = '.section-teaser .featured-inner';
	homepageSlider.init();
	
	//Move the list of promo categories below the controls to allow all content to expand
	$('.teaser-header').insertBefore(".slideshow-controls"); 
    
	var headerListItems = $('.section-teaser .teaser-header li a');
	
	headerListItems.click(function() {
		headerListItems.parent('li').removeClass('selected');
		$(this).parent('li').addClass('selected');
		homepageSlider.moveToItem(headerListItems.index(this) + 1);
		homepageSlider.scroll = false;
		return false;
	});
	
	// Select the first one
	$('.section-teaser .teaser-header li').eq(0).addClass('selected');	

    if ($(document.body).hasClass('user-login')) {
        // If the user login detected, switch to the second item to skip intro.
        homepageSlider.moveToItem(2);
    } else {
        // Show the intro to anon users for 5 visits, then switch to second item.
        var SEEN_COOKIE = 'amo_home_promo_seen',
            MAX_SEEN    = 5,
            times_seen  = parseInt($.cookie(SEEN_COOKIE));
        if (!times_seen) times_seen = 0;

        if (times_seen >= MAX_SEEN) {
            // If the intro has been seen enough times, skip it.
            homepageSlider.moveToItem(2);
        } else {
            // Otherwise, count another appearance and stash in a cookie.
            $.cookie(SEEN_COOKIE, times_seen + 1, {
                path: '/',
                expires: (new Date()).getTime() + ( 1000 * 60 * 60 * 24 * 365 )
            });
        }
    }

});
