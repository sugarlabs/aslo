(function($) {
	window.Slideshow = function() {
		this.itemTotal = 0;
		this.currentItem = 1;
		this.itemWidth = 0;
		
		//  Set these properties when you instantiate an instance of this object.
		this.speed = 300; // the speed in milliseconds of the animation

		this.itemContainer = ''; // the selector for the element containing the items.
        this.wrapperElement = ''; // the tagName that will wrap the itemContainer.
		this.wrapperClass = ''; //the classname of the element that will wrap the itemContainer.
		this.controlsMarkup = ''; // the markup for the controls.
		this.leftController = ''; // the selector for the left controller.
		this.rightContorller = ''; // the selector for the right controller.
		this.activeClass = '';  // the classname to indicate that a controller is active.
		this.container = ''; //the complete container for all of the slideshow
		this.interval = null;
		this.scroll = true;
	};
	Slideshow.prototype.init = function() {
		this.itemTotal = parseInt($(this.itemContainer+'>li').length,10);
		if (this.itemTotal <= 1) {
			return;
		}

		$(this.itemContainer).wrap('<'+this.wrapperElement+' class="'+this.wrapperClass+'"></'+this.wrapperElement+'>');
		this.itemWidth = this.getItemWidth();
		// applying controls to 2nd parent rather than 1st fixes stacking context issue in FF2
		$($(this.itemContainer).parents()[1]).append(this.controlsMarkup);
		$(this.itemContainer+'>li').width(this.itemWidth+'px');

		this.checkControls();

		var self = this;
		$(self.leftController).live('click', function() {
			if ($(this).hasClass(self.activeClass)) {
				self.moveToItem(self.currentItem-1);
			}
			self.scroll = false;
			return false;
		});

		$(self.rightController).live('click', function() {
			if ($(this).hasClass(self.activeClass)) {
				self.moveToItem(self.currentItem+1);
			}
			self.scroll = false;
			return false;
		});
        
        $(self.container).mouseenter(function() {
            clearInterval(self.interval);
        });
        
        $(self.container).mouseleave(function() {
            self.autoRotate();
        })
        
        self.autoRotate();
        
		$(window).resize(function() {
			self.itemWidth = self.getItemWidth();
			$(self.itemContainer+'>li').width(self.itemWidth+'px');
			self.popToItem(self.currentItem);
		});
	};
	
	Slideshow.prototype.autoRotate = function() {
	    if(this.scroll) {
	        var that = this; //closure due to setInterval's 'this' refers to window, not the current 'this'
    	    this.interval = setInterval(function() {
    	        if(that.currentItem != that.itemTotal) {
                    that.moveToItem(that.currentItem+1);
                } else {
                    that.moveToItem(1);
                }
    	    }, 8000);
	    }
	};
	
	Slideshow.prototype.getItemWidth = function() {
		return $(this.itemContainer).parents('.'+this.wrapperClass).width();
	};
	Slideshow.prototype.popToItem = function(itemNumber) {
		if (!$(this.itemContainer).parents('.'+this.wrapperClass+' :animated').length) {
			var endpoint = (itemNumber-1) * this.itemWidth * -1;
			$(this.itemContainer).css({'left':(endpoint+'px')});
			this.currentItem = itemNumber;
			this.checkControls();
		}
	};
	Slideshow.prototype.moveToItem = function(itemNumber) {
		if (!$(this.itemContainer).parents('.'+this.wrapperClass+' :animated').length) {
			var endpoint = (itemNumber-1) * this.itemWidth * -1;
			$(this.itemContainer).animate({'left':(endpoint+'px')},this.speed);
			this.currentItem = itemNumber;
			this.checkControls();
		}
	};
	Slideshow.prototype.checkControls = function() {
		if (this.currentItem == 1) {
			$(this.leftController).removeClass(this.activeClass);
		} else {
			$(this.leftController).addClass(this.activeClass);
		}
		if (this.currentItem == this.itemTotal) {
			$(this.rightController).removeClass(this.activeClass);
		} else {
			$(this.rightController).addClass(this.activeClass);
		}
	};
	
	// slidey dropdown area 
	window.DropdownArea = function() {
		this.trigger = null;
		this.target = '';
		this.targetParent = '';
		this.callbackFunction = function(){};
		this.preventDefault = true;
		this.showSpeed = 200;
		this.hideSpeed = 200;
	};
	DropdownArea.prototype.bodyclick = function(e) {
		// this will get fired on click of body, we need to close the dropdown
		if (this.bodyWatching) {
			if (!
				($(e.target).get(0) == $(this.targetParent).get(0) ||
				 $(e.target).parents(this.targetParent).length)
			) {
			    this.hide();
			}
			
		}
	}
	DropdownArea.prototype.hide = function() {
		var self = this;
		$(self.targetParent).removeClass('expanded');
		$(self.target).slideUp(self.hideSpeed, function() {
    		//unbind bodyclick
    		self.bodyWatching = false;
		});
	}
	DropdownArea.prototype.show = function() {
		var self = this;
		$(self.targetParent).addClass('expanded');
		$(self.target).slideDown(self.showSpeed, function() {
			self.bodyWatching = true;
		});
	}
	DropdownArea.prototype.init = function() {
		// advanced dropdown 
		$(this.target).hide();
		var self = this;
		if (this.trigger) {
			this.trigger.click( 
				function(e) {
					if(! $(self.target+':animated').length) {
						if ($(self.target+':visible').length){
						    self.hide();
						} else {
							self.callbackFunction();
							self.show();
						}
					}
					$(self.target).trigger('click');
					return !self.preventDefault;
				}
			);
			// if box now showing bind bodyclick
			$('body').bind("click", function(e) {
				self.bodyclick(e);
			});
		}
	};
})(jQuery);

jQuery(function($) {
	//	Add the class "hasJS" to the body element.
	$('body').addClass('hasJS');

	// Greys out the favourites icon when it is clicked
    $(".item-info li.favourite").click(function () {
	  var self = this;
	  $(self).addClass("favourite-loading");
	  setTimeout(function() {
	    $(self).addClass("favourite-added");
	  },2000);
    });

	function selectReplacement(obj) {
		obj.className += ' replaced';
		var ul = document.createElement('ul');
		ul.className = 'selectReplacement';
		var opts = obj.options;
		for (var i=0; i<opts.length; i++) {
			var selectedOpt;
			if (opts[i].selected) {
				selectedOpt = i;
				break;
			} else {
				selectedOpt = 0;
			}
		}
		for (var i=0; i<opts.length; i++) {
			var li = document.createElement('li');
			var link = document.createElement('a');
			li.appendChild(link);
			li.className = opts[i].className;
			link.selIndex = opts[i].index;
			link.selectID = obj.id;
			link.setAttribute('href','#');
			link.onclick = function() {
				selectMe(this);
				return false;
			}
			if (i == selectedOpt) {
				ul.className = 'selectReplacement '+opts[i].className;
			}
			ul.appendChild(li);
		}
		obj.parentNode.insertBefore(ul,obj);
	}
	function selectMe(obj) {
		setVal(obj.selectID, obj.selIndex);
		var list = obj.parentNode.parentNode;
		list.className = 'selectReplacement '+obj.parentNode.className;
	}
	function setVal(objID, selIndex) {
		var obj = document.getElementById(objID);
		obj.selectedIndex = selIndex;
	}
	if (document.getElementById('review-rating')) {
		selectReplacement(document.getElementById('review-rating'));
	}
	
	// Categories dropdown only on pages where it is not in secondary
	if($('.categories').parents('.secondary').length == 0) {
		var categories = new DropdownArea();
		// add class to style differently
		$('.categories').addClass('dropdown-categories');
	
		// set up images for dropdown
        var categoryContainer = $('.categories :first-child')[0];
        if (categoryContainer) {
            var clickableCategories = $(categoryContainer);
            clickableCategories.prepend('<img src="/img/amo2009/icons/category-dropdown-down.gif" alt="" /> ');
            
            // stop the accidental selection during double click
            clickableCategories.each(function(){
                this.onselectstart = function () { return false; }
                this.onmousedown = function () { return false; }
            });
        
            // set up variables for object
            categories.trigger = clickableCategories; // node
            categories.target = '.categories>ul'; // reference
            categories.targetParent = '.categories'; // reference
            categories.callbackFunction = function() {
                if($('.categories>ul:visible').length){
                    $('.categories img').attr('src', '/img/amo2009/icons/category-dropdown-down.gif');
                } else {
                    $('.categories img').attr('src', '/img/amo2009/icons/category-dropdown-up.gif');
                }
            };
            
            // initialise dropdown area 
            categories.init();
        }
	}
	

	// advanced form dropdown
	var advancedForm = new DropdownArea();
	// set up variables for object
	advancedForm.trigger = ($('#advanced-link a')); // node
	advancedForm.target = ('.advanced'); // reference
	advancedForm.targetParent = ('.search-form'); // reference
	advancedForm.init();
	
	// tools dropdown in auxillary menu
	var toolsDropdown = new DropdownArea();
	// set up variables for object
	toolsDropdown.trigger = ($('ul.tools .controller')); // node
	toolsDropdown.target = ('ul.tools ul'); // reference
	toolsDropdown.targetParent = ('ul.tools'); // reference
	toolsDropdown.init();
	
	// change dropdown in auxillary menu
	var changeDropdown = new DropdownArea();
	// set up variables for object
	changeDropdown.trigger = ($('ul.change .controller')); // node
	changeDropdown.target = ('ul.change ul'); // reference
	changeDropdown.targetParent = ('ul.change'); // reference
	changeDropdown.init();

	// share dropdown
	var shareDropdown = new DropdownArea();
	// set up variables for object
	shareDropdown.trigger = ($('.share-this a.share')); // node
	shareDropdown.target = ('.share-this .share-networks'); // reference
	shareDropdown.targetParent = ('.share-this'); // reference
	shareDropdown.init();
	
	// notification dropdown
	var notificationHelpDropdown = new DropdownArea();
	// set up variables for object
	notificationHelpDropdown.trigger = ($('.notification .toggle-help')); // node
	notificationHelpDropdown.target = ('.notification .toggle-info'); // reference
	notificationHelpDropdown.targetParent = ('.notification'); // reference
	notificationHelpDropdown.init();
	$('.notification .toggle-info').append('<a href="#" class="close">close</a>')
	$('.notification a.close').click(function() {
		notificationHelpDropdown.hide();
		return false;
	})
	
	// listing where interaction is inline
	$('.home .listing div:first').addClass('interactive');
	
	function tabClickFactory(className) {
		return function(){
			$(this).parents('ul').find('li').removeClass('selected');
			$($(this).parents('li')[0]).addClass('selected');
			$(this).parents('.listing').attr('class','featured listing');
			$(this).parents('.listing').addClass(className);
			return false;
		}
	}
	$(".home a[href^='#recommended']").click(tabClickFactory('show-recommended'));
	$(".home a[href^='#popular']").click(tabClickFactory('show-popular'));
	$(".home a[href^='#added']").click(tabClickFactory('show-added'));
	$(".home a[href^='#updated']").click(tabClickFactory('show-updated'));
	
	$('a.screenshot.thumbnail').append('<img src="/img/amo2009/icons/resize.png" alt="click to expand image" class="img-control"/>')

});

jQuery(window).load(function() {
	// Crazyweird fix lets us style abbr using CSS in IE 
	// - do NOT run onDomReady, must be onload
	document.createElement('abbr');
});
