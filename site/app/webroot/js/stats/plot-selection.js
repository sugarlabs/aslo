var plotSelection = {
    summary: {}, // JSON summary retrieved with field names and other data
    dropdowns: {},
    showContributions: false,
    
    /**************************************
     *       Dropdown Data Utilities      *
     *************************************/
    
    /**
     * Loads plotSelection.summary data from JSON
     */
    loadSummary: function() {
        $.getJSON(statsURL + 'json/' + addonID + '/summary', function(data) {
            plotSelection.summary = data;
        });
        
    },
    
    /**************************************
     *    Dropdown Creation Utilities     *
     *************************************/
    
    addPlotSelector: function() {
        var dropdown = new Dropdown({
                'id': 'plot-selector',
                'type': 'big-menu',
                'onChange': 'Plots.determinePlot();',
                'hasColorbox': false,
                'parentDOM': 'plot-selector-area',
                'removeOnNewTimeplot': false
            });
        
        this.dropdowns[dropdown.config.id] = dropdown;
        
        var menu = this.dropdowns[dropdown.config.id].addMenu({
                'name': 'plotselectionmenu',
                'showNoneForLevel1': false
            });
        
        menu.addItem({'value': 'summary', 'name': localized['statistics_js_plotselection_selector_summary']}).select();
        menu.addItem({'value': 'downloads', 'name': localized['statistics_js_plotselection_selector_downloads']});
        menu.addItem({'value': 'updatepings', 'name': localized['statistics_js_plotselection_selector_adu']});
        menu.addItem({'value': 'version', 'name': localized['statistics_js_plotselection_selector_version'], 'indented': true});
        menu.addItem({'value': 'application', 'name': localized['statistics_js_plotselection_selector_application'], 'indented': true});
        menu.addItem({'value': 'status', 'name': localized['statistics_js_plotselection_selector_status'], 'indented': true});
        menu.addItem({'value': 'os', 'name': localized['statistics_js_plotselection_selector_os'], 'indented': true});
        if (this.showContributions) {
            menu.addItem({'value': 'contributions', 'name': localized['statistics_js_plotselection_selector_contributions']});
        }
        //menu.addItem({'value': 'custom', 'name': localized['statistics_js_plotselection_selector_custom']});
    },

    addGroupBySelector: function() {
        var group_drop = new Dropdown({
            'id':          'group-by-selector',
            'type':        'group-by',
            'onChange':    'Plots.determinePlot();',
            'hasColorbox': false,
            'parentDOM':   'plot-selector-area',
            'removeOnNewTimeplot': false
        });
        this.dropdowns['group-by-selector'] = group_drop;
        var menu = group_drop.addMenu({
            'name': 'group-by-selection-menu',
            'showNoneForLevel1': false
        });

        menu.addItem({'value': 'date',  'name': localized['statistics_js_groupby_selector_date']}).select();
        menu.addItem({'value': 'week',  'name': localized['statistics_js_groupby_selector_week']});
        menu.addItem({'value': 'month', 'name': localized['statistics_js_groupby_selector_month']});
        menu.addItem({'value': 'week_over_week', 'name':localized['statistics_js_groupby_selector_week_over_week']});

        $('#group-by-selector').hide();
    },

    getGroupByValue: function() {
        return (!this.dropdowns['group-by-selector']) ? 'date' :
            this.dropdowns['group-by-selector'].selectedItem.value;
    },
    
    addAdditionalDropdown: function() {
        var type = plotSelection.dropdowns['plot-selector'].selectedItem.value;
        
        if (type == 'custom')
            this.addCustomDropdown();
        else
            this.addDefinedDropdown();
    },
    
    /**
     * Adds a custom dropdown with menus and submenus from summary
     */
    addCustomDropdown: function() {
        // Add dropdown
        var dropdown = new Dropdown({
                'type': 'custom', 'removeOnNewTimeplot': true
            });
        
        this.dropdowns[dropdown.config.id] = dropdown;
        
        // Add data type menu
        var typeMenu = this.dropdowns[dropdown.config.id].addMenu({
                'type': 'custom'
            });
        
        $.each(this.summary.updatepings.plotFields, function(field, values) {
            // Add data type items
            var typeItem = typeMenu.addItem({
                    'value': field,
                    'name': plotSelection.summary.prettyNames[field],
                    'isSubmenu': true
                });
            
            // Loop through values (versions, statuses, app names, etc)
            $.each(values, function(item, count) {
                if (typeof count != 'object') {
                    // If not an object, regular menu item
                    typeItem.submenu.addItem({
                            'value': item,
                            'name': item,
                            'tooltip': sprintf(localized['statistics_js_plotselection_foundinrange'], count)
                        });
                }
                else {
                    // If an object, it's an application
                    
                    // Filter GUID to make for better id=""
                    var filtered = item.replace(/[\s\@\.\?\{\}\-\"%<>]/g, '_');
                    
                    // Add application name item
                    var appName = (item in plotSelection.summary.prettyNames ? plotSelection.summary.prettyNames[item] : plotSelection.summary.prettyNames['unknown'] + ' ' + item);
                    
                    var appItem = typeItem.submenu.addItem({
                            'value': filtered,
                            'name': appName,
                            'tooltip': 'GUID: ' + item,
                            'isSubmenu': true
                        });
                    
                    // Loop through application versions
                    $.each(count, function(appVersion, appCount) {
                        // Add item for application version
                        appItem.submenu.addItem({
                            'value': appVersion,
                            'name': appVersion,
                            'tooltip': sprintf(localized['statistics_js_plotselection_foundinrange'], appCount)
                            });
                    });
                }
            });
        });
    },
    
    addDefinedDropdowns: function() {
        this.addOptionsDropdown();
        
        for (var i = 2; i < 8; i++) {
            if (Plots.availableFields[i]) {
                this.addDefinedDropdown(i);
            }
        }
        
    },
    
    addDefinedDropdown: function(selectedIndex) {
        var type = this.dropdowns['plot-selector'].selectedItem.value;
        
        var dropdown = new Dropdown({
                'type': type,
                'onChange': 'plotSelection.definedChanged(this);'
            });
        
        this.dropdowns[dropdown.config.id] = dropdown;
        
        var menu = this.dropdowns[dropdown.config.id].addMenu({
                'name': type,
                'scrolling': true
            });
        
        var item;
        
        if (type == 'application') {
            var appMenus = {};
        }
        
        for (var j = 2; j < Plots.availableFields.length; j++) {
            if (type == 'application') {
                var appInfo = this.getApplicationName(Plots.availableFields[j]);
                
                if (!appMenus[appInfo.guid]) {
                    // Filter GUID to make for better id=""
                    var filtered = appInfo.guid.replace(/[\s\@\.\?\{\}\-\"%<>]/g, '_');
                    
                    // Add application name item
                    var appName = (appInfo.guid in plotSelection.summary.prettyNames ? plotSelection.summary.prettyNames[appInfo.guid] : plotSelection.summary.prettyNames['unknown'] + ' ' + appInfo.guid);
                    
                    appMenus[appInfo.guid] = menu.addItem({
                            'value': filtered,
                            'name': appName,
                            'tooltip': 'GUID: ' + appInfo.guid,
                            'isSubmenu': true
                        });
                }
                
                item = appMenus[appInfo.guid].submenu.addItem({
                        'value': Plots.availableFields[j],
                        'name': appInfo.itemName,
                        'tooltip': appInfo.itemTooltip,
                        'prefix': (plotSelection.summary.shortNames[type] ? plotSelection.summary.shortNames[type] + ':' : '')
                    });
            }
            else
                item = menu.addItem({
                        'value': Plots.availableFields[j],
                        'name': Plots.availableFields[j],
                        'prefix': (plotSelection.summary.shortNames[type] ? plotSelection.summary.shortNames[type] + ':' : '')
                    });
            
            
            if (selectedIndex == j)
                item.select();
        }
    },
    
    getApplicationName: function(guid) {
        var appParts = guid.split('/');
        if (plotSelection.summary.shortNames[plotSelection.summary.prettyNames[appParts[0]]]) {
            var itemName = plotSelection.summary.shortNames[plotSelection.summary.prettyNames[appParts[0]]] + '&nbsp;' + appParts[1];
            var itemTooltip = appParts[0] + ' (' + plotSelection.summary.prettyNames[appParts[0]] + ')';
        }
        else {
            var itemName = plotSelection.summary.shortNames['unknown'] + '&nbsp;' + appParts[1];
            var itemTooltip = appParts[0] + ' (' + plotSelection.summary.prettyNames['unknown'] + ')';
        }
        
        return {'guid': appParts[0], 'version': appParts[1], 'itemName': itemName, 'itemTooltip': itemTooltip};
    },
    
    definedChanged: function(dropdown) {
        // Remove existing plot
        Plots.defined.removePlot(dropdown.config.id);
        
        // If not clearing the plot, add the new selected plot
        if (dropdown.selectedItem.value != '') {
            Plots.defined.addPlot(dropdown.config.id, Plots.availableFields.indexOf(dropdown.selectedItem.value), dropdown.config.color);
        }
    },
    
    addOptionsDropdown: function() {
        var type = this.dropdowns['plot-selector'].selectedItem.value;
        
        var dropdown = new Dropdown({
                'id': 'options',
                'type': 'options',
                'title': '<img src="' + $('#options-cog').attr('src') + '" />',
                'removeOnNewTimeplot': true,
                'removable': false,
                'hasColorbox': false,
                'itemsToggle': true,
                'parentDOM': 'options-area'
            });
        
        this.dropdowns[dropdown.config.id] = dropdown;
        
        var menu = this.dropdowns[dropdown.config.id].addMenu({
                'name': 'options',
                'showNoneForLevel1': false
            });
        
        if (type != 'summary' && type != 'downloads' && type != 'contributions') {
            menu.addItem({
                    'value': 'count',
                    'name': localized['statistics_js_plotselection_options_count_name_checked'],
                    'nameChecked': localized['statistics_js_plotselection_options_count_name_checked'],
                    'nameUnchecked': localized['statistics_js_plotselection_options_count_name_unchecked'],
                    'tooltip': localized['statistics_js_plotselection_options_count_tooltip'],
                    'checked': true,
                    'onSelect': 'plotSelection.togglePlot(this);',
                    'addValueToClass': true
                });
        }
        
        if (type != 'summary') {
            menu.addItem({
                    'value': 'events-firefox',
                    'name': localized['statistics_js_plotselection_options_events_firefox_name_checked'],
                    'nameChecked': localized['statistics_js_plotselection_options_events_firefox_name_checked'],
                    'nameUnchecked': localized['statistics_js_plotselection_options_events_firefox_name_unchecked'],
                    'tooltip': localized['statistics_js_plotselection_options_events_firefox_tooltip'],
                    'checked': true,
                    'onSelect': 'plotSelection.toggleEventPlot(this);',
                    'addValueToClass': true
                });
            
            var addonItem = menu.addItem({
                    'value': 'events-addon',
                    'name': sprintf(localized['statistics_js_plotselection_options_events_addon_name_checked'], addonName),
                    'nameChecked': sprintf(localized['statistics_js_plotselection_options_events_addon_name_checked'], addonName),
                    'nameUnchecked': sprintf(localized['statistics_js_plotselection_options_events_addon_name_unchecked'], addonName),
                    'tooltip': localized['statistics_js_plotselection_options_events_addon_tooltip'],
                    'checked': true,
                    'onSelect': 'plotSelection.toggleEventPlot(this);',
                    'addValueToClass': true
                });
            $('#' + addonItem.config.id + ' .item-toggle-icon').html('<img src="' + $('#addon-icon').attr('src') + '" width="16" height="16" />');
        }
        
        if (type != 'summary' && type != 'downloads' && type != 'contributions') {
            menu.addItem({
                    'value': 'add-plot',
                    'name': localized['statistics_js_plotselection_options_addplot_name'],
                    'tooltip': localized['statistics_js_plotselection_options_addplot_tooltip'],
                    'onSelect': 'plotSelection.addAdditionalDropdown();',
                    'addValueToClass': true
                });
        }
        
        if (type != 'summary')
            menu.addDivider();
        
        menu.addItem({
                'value': 'resize',
                'name': localized['statistics_js_plotselection_options_resize_name_unchecked'],
                'nameChecked': localized['statistics_js_plotselection_options_resize_name_checked'],
                'nameUnchecked': localized['statistics_js_plotselection_options_resize_name_unchecked'],
                'tooltip': localized['statistics_js_plotselection_options_resize_tooltip'],
                'onSelect': 'plotSelection.resizePlot(this);',
                'addValueToClass': true
            });
        
        /*menu.addItem({
                'value': 'rss',
                'name': 'Subscribe to Graph',
                'tooltip': 'Subscribe to this graph for daily updates',
                'addValueToClass': true
            });*/
        
        var csvItem = menu.addItem({
                'value': 'csv',
                'name': localized['statistics_js_plotselection_options_csv_name'],
                'tooltip': localized['statistics_js_plotselection_options_csv_tooltip'],
                'addValueToClass': true
            });
        $('#' + csvItem.config.id + ' a').attr('href', Plots.currentCSV);
    },
    
    togglePlot: function(item) {
        if (item.config.checked)
            Plots.defined.addPlot(item.config.value, 1, '#000000');
        else
            Plots.defined.removePlot(item.config.value);
    },
    
    toggleEventPlot: function(item) {
        if (item.config.checked)
            Plots.defined.addEventPlot(item.config.value);
        else
            Plots.defined.removePlot(item.config.value);
    },
    
    resizePlot: function(item) {
        if (item.config.checked)
            Plots.resizePlot(350);
        else
            Plots.resizePlot(150);
    },
    
    remove: function(dropdown) {
        Plots.defined.removePlot(dropdown.config.id);
        plotSelection.dropdowns[dropdown.config.id] = null;
    },
    
    removeAll: function() {
        colors.resetCounter();
        
        $.each(plotSelection.dropdowns, function(dropdown_id, object) {
            if (plotSelection.dropdowns[dropdown_id] != null) {
                if (plotSelection.dropdowns[dropdown_id].remove(true))
                    plotSelection.dropdowns[dropdown_id] = null;
            }
        });
    }
};
