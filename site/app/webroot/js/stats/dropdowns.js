var totalDropdowns = 0;

/**
 * Initializes settings for new dropdown
 *
 * @param options object Any non-default config values
 */
Dropdown = function(options) {
    if (!options)
        options = {};
    
    var config = {
        'id': (options.id ? options.id : ''),
        'color': (options.color ? options.color : ''),
        'title': (options.title ? options.title : ''),
        'type': (options.type ? options.type : ''),
        'itemsToggle': (typeof options.itemsToggle != 'undefined' ? options.itemsToggle : false),
        'removable': (typeof options.removable != 'undefined' ? options.removable : true),
        'removeOnNewTimeplot': (typeof options.removeOnNewTimeplot != 'undefined' ? options.removeOnNewTimeplot : true),
        'hasColorbox': (typeof options.hasColorbox != 'undefined' ? options.hasColorbox : true),
        'onChange': (options.onChange ? options.onChange : null),
        'dropdownContainerObject': (options.dropdownContainerObject ? options.dropdownContainerObject : 'plotSelection.dropdowns'),
        'parentDOM': (options.parentDOM ? options.parentDOM : 'plot-selection')
    };
    
    this.config = config;
    
    // Insert dropdown into DOM
    this.insert();
};

Dropdown.prototype = {
    selectedItem: null,
    callString: '',
    
    /**
     * Generates and inserts the new dropdown with the next available plot
     * color and id.
     */
    insert: function() {
        // Get next available plot color if not specified
        if (!this.config.color && this.config.hasColorbox)
            this.config.color = colors.getNext();
        
        totalDropdowns++;
        
        // Get next available plot id if not specified
        if (!this.config.id)
            this.config.id = 'plot' + totalDropdowns;
        
        // Set call string for easier reference
        this.callString = this.config.dropdownContainerObject + "['" + this.config.id + "']";
        
        // Build HTML
        var dropdown = '<div id="' + this.config.id + '" class="plot-dropdown ';
        dropdown += this.config.type;
        if (this.config.itemsToggle)
            dropdown += ' toggle';
        dropdown += '">';
        if (this.config.hasColorbox) {
            dropdown += '<div class="colorbox ';
            if (this.config.removable) {
                dropdown += 'removable" title="' + localized['statistics_js_dropdowns_removeplot'] + '" ';
                dropdown += 'onclick="' + this.callString + '.remove(false, \'plotSelection.remove(this);\');';
            }
            dropdown += '" style="background-color: ' + this.config.color + ';"></div>';
        }
        dropdown += '<a onclick="' + this.callString + '.toggle();">';
        dropdown += '<div class="selected"><div class="selected-text">';
        dropdown += '<span class="selected-prefix" style="color: ' + this.config.color + ';"></span>';
        dropdown += '<span class="selected-name">';
        if (this.config.title != '')
            dropdown += this.config.title;
        dropdown += '</span></div></div>';
        dropdown += '</a></div>';
        
        // Append the new dropdown
        $('#' + this.config.parentDOM).append(dropdown);
    },
    
    /**
     * Removes the dropdown from the DOM
     *
     * @param newTimeplot boolean Whether this is being called because of a new
     * timeplot creation.
     * @param callback string A callback function for after the removal
     */
    remove: function(newTimeplot, callback) {
        if (newTimeplot && !this.config.removeOnNewTimeplot)
            return false;
        
        $('#' + this.config.id).remove();
        
        if (callback)
            eval(callback);
        
        return true;
    },
    
    /**
     * Opens the dropdown if closed and closes the dropdown if open
     */
    toggle: function() {
        if ($('#' + this.config.id + ' > ul').is(':hidden'))
            this.open();
        else
            this.close();
    },
    
    /**
     * Opens the dropdown
     */
    open: function() {
        // Close any open dropdowns
        this.closeAll();
        
        $('#' + this.config.id).addClass('menu-open');
        $('#' + this.config.id + ' > ul.level1').slideDown('normal', function() {
            // Add listener to close the dropdown if user clicks off of the menu
            $(document).click(offClickHandler);
        });
    },
    
    /**
     * Close only this dropdown
     */
    close: function() {
        var id = this.config.id;
        
        // Remove offClick listener
        $(document).unbind('click', offClickHandler);
        
        $('#' + id + ' > ul').slideUp();
        $('#' + id).removeClass('menu-open');
    },
    
    /**
     * Close all open dropdowns
     */
    closeAll: function() {
        // Remove offClick listener
        $(document).unbind('click', offClickHandler);
        
        // Close all open dropdowns in order of hierarchy
        $('.plot-dropdown > ul.level3:visible').animate({width: 'hide'}, 'fast');
        $('.plot-dropdown > ul.level2:visible').animate({width: 'hide'}, 'fast');
        $('.plot-dropdown > ul.level1:visible').slideUp('fast');
        $('.plot-dropdown').removeClass('menu-open');
    },
    
    /**
     * Updates the dropdown box DOM with the currently selected item
     */
    updateSelection: function() {
        var id = this.config.id;
        
        $('#' + id + ' .selected-prefix').html(this.selectedItem.prefix);
        $('#' + id + ' .selected-name').html(this.selectedItem.name);
        $('#' + id + ' .selected-name').attr('title', this.selectedItem.tooltip);
        
        // Close dropdown
        this.close();
        
        // Execute any onChange events the dropdown has
        if (this.config.onChange)
            eval(this.config.onChange);
    },
    
    /*************************************************************************/
    
    menus: {},
    
    /**
     * Add a new menu to this dropdown
     *
     * @param options object Any non-default config values for the menu
     */
    addMenu: function(options) {
        var menu = new Dropdown.Menu(this, options);
        this.menus[menu.config.id] = menu;
        
        return menu;
    }
    
};

var ignoreNextOffClick = false;
/**
 * Handles an offClick event fire
 */
function offClickHandler() {
    if (ignoreNextOffClick)
        ignoreNextOffClick = false;
    else
        Dropdown.prototype.closeAll();
}

/**
 * Initialize the new menu settings
 *
 * @param dropdown object Reference to dropdown object
 * @param options object Any non-default config values
 */
Dropdown.Menu = function Dropdown_Menu(dropdown, options) {
    // Save reference to dropdown object
    this.dropdown = dropdown;
    
    // Set menu config defaults
    if (!options)
        options = {};
    
    var config = {
        'level': (options.level ? options.level : 1),
        'name': (options.name ? options.name : ''),
        'scrolling': (typeof options.scrolling != 'undefined' ? options.scrolling : false),
        'showNoneForLevel1': (typeof options.showNoneForLevel1 != 'undefined' ? options.showNoneForLevel1 : true)
    };
    
    this.config = config;
    
    // Insert menu into DOM
    this.insert();
};

Dropdown.Menu.prototype = {
    callString: '',
    
    /**
     * Insert the new menu into the DOM
     */
    insert: function() {
        // Generate menu id
        this.config.id = this.dropdown.config.id + '_' + this.config.name;
        
        // Set callString for easier reference
        this.callString = this.dropdown.callString + ".menus['" + this.config.id + "']";
        
        // Build HTML
        var menu = '<ul id="' + this.config.id + '" class="level' + this.config.level;
        if (this.config.scrolling)
            menu += ' scrolling';
        menu += '"></ul>';
        
        // Append the new menu
        $('#' + this.dropdown.config.id).append(menu);
        
        // If level 1, show <none> as first menu item and select it
        if (this.config.level == 1 && this.config.showNoneForLevel1)
            this.addItem({'name': '<span class="none">&lt;' + localized['statistics_js_dropdowns_none'] + '&gt;</span>'}).select();
    },
    
    /**
     * Shows a menu or submenu
     *
     * @param a object The link object that called the method.
     */
    show: function(a) {
        // Only show if the submenu is not already open
        if ($('#' + this.config.id).is(':hidden')) {
            $(a).addClass('active-item');
            $('#' + this.config.id).animate({width: 'show'});
        }
    },
    
    /**
     * Hides a menu or submenu
     */
    hide: function(trigger) {
        if (trigger)
            var except = ':not(#' + $(trigger).attr('opens') + ')';
        else
            var except = '';
        
        if (this.config.level < 3) {
            $('.plot-dropdown > ul.level3:visible' + except).animate({width: 'hide'}, 'fast');
            $('.plot-dropdown > ul.level3 .active-item').removeClass('active-item');
            $('.plot-dropdown > ul.level2 .active-item').removeClass('active-item');
        }
        
        if (this.config.level < 2) {
            $('.plot-dropdown > ul.level2:visible' + except).animate({width: 'hide'}, 'fast');
            $('.plot-dropdown > ul .active-item').removeClass('active-item');
        }
    },

    /*************************************************************************/
    
    items: {},
    
    /**
     * Add a new item to this menu
     *
     * @param options object Any non-default config values for the new item
     */
    addItem: function(options) {
        var item = new Dropdown.Menu.Item(this, options);
        this.items[item.config.id] = item;
        
        return item;
    },
    
    addDivider: function() {
        // Build HTML
        var divider = '<li class="menu-divider"></li>';
        
        $('#' + this.config.id).append(divider);
    }
};

/**
 * Initialize the new item settings
 *
 * @param menu object Reference to the parent menu object
 * @param options object Any non-default config values
 */
Dropdown.Menu.Item = function Dropdown_Menu_Item(menu, options) {
    // Save reference to menu object
    this.menu = menu;
    
    // Set item config defaults
    if (!options)
        options = {};
    
    var config = {
        'value': (options.value ? options.value : ''),
        'name': (options.name ? options.name : ''),
        'nameChecked': (options.nameChecked ? options.nameChecked : ''),
        'nameUnchecked': (options.nameUnchecked ? options.nameUnchecked : ''),
        'tooltip': (options.tooltip ? options.tooltip : ''),
        'checked': (typeof options.checked != 'undefined' ? options.checked : false),
        'isSubmenu': (typeof options.isSubmenu != 'undefined' ? options.isSubmenu : false),
        'prefix': (options.prefix ? options.prefix : ''),
        'onSelect': (options.onSelect ? options.onSelect : null),
        'addValueToClass': (typeof options.addValueToClass != 'undefined' ? options.addValueToClass : false),
        'indented': (typeof options.indented != 'undefined' ? options.indented : false)
    };
    
    this.config = config;
    
    // Insert item into DOM
    this.insert();
};

Dropdown.Menu.Item.prototype = {
    callString: '',
    
    /**
     * Inserts the new item into the DOM
     */
    insert: function() {
        // Generate item id
        this.config.id = this.menu.config.id + '_' + this.config.value;
        
        // Set callString for easier reference
        this.callString = this.menu.callString + ".items['" + this.config.id + "']";
        
        // Build HTML
        var item = '<li id="' + this.config.id + '" class="plot-item';
        if (this.config.isSubmenu)
            item += ' submenu';
        if (this.config.addValueToClass)
            item += ' ' + this.config.value;
        if (this.config.checked)
            item += ' checked';
        if (this.config.indented)
            item += ' indented';
        item += '"><a title="' + this.config.tooltip + '" ';
        
        if (this.config.isSubmenu) {
            // Create the submenu
            this.submenu = this.menu.dropdown.addMenu({
                'name': this.config.value,
                'level': (this.menu.config.level + 1),
                'scrolling': this.menu.config.scrolling
            });
            
            item += 'opens="' + this.submenu.config.id + '" ';
            
            // Submenu items show their submenus on click and mouseover
            item += 'onclick="ignoreNextOffClick = true;';
            item += this.submenu.callString + '.show(this);" ';
            item += 'onmouseover="' + this.menu.callString + '.hide(this); ';
            item += this.submenu.callString + '.show(this);">';
        }
        else {
            // regular items close any open submenus on mouseover and select themselves on click
            item += 'onmouseover="' + this.menu.callString + '.hide();" ';
            item += 'onclick="' + this.callString + '.select(this);">';
        }
        
        if (this.menu.dropdown.config.itemsToggle)
            item += '<div class="item-toggle-icon"></div>';
        
        item += '<span class="item-name">' + this.config.name + '</span></a></li>';
        
        $('#' + this.menu.config.id).append(item);
    },
    
    /**
     * Selects the current item in the dropdown and calls to update the dropdown
     * DOM
     *
     * @param a object DOM link that called the selection. Not used here but may
     *                 be used in an onSelect callback. (Optional)
     */
    select: function(a) {
        if (!this.menu.dropdown.config.itemsToggle) {
            this.menu.dropdown.selectedItem = this.config;
            
            this.menu.dropdown.updateSelection();
        }
        else {
            ignoreNextOffClick = true;
            
            if (this.config.checked) {
                this.config.checked = false;
                $('#' + this.config.id).removeClass('checked');
                
                if (this.config.nameUnchecked != '') {
                    this.config.name = this.config.nameUnchecked;
                    this.refreshDOM();
                }
            }
            else {
                this.config.checked = true;
                $('#' + this.config.id).addClass('checked');
                
                if (this.config.nameChecked != '') {
                    this.config.name = this.config.nameChecked;
                    this.refreshDOM();
                }
            }
        }
        
        // Execute any onSelect events the item has
        if (this.config.onSelect)
            eval(this.config.onSelect);
    },
    
    refreshDOM: function() {
        $('#' + this.config.id + ' .item-name').html(this.config.name);
    }
};
