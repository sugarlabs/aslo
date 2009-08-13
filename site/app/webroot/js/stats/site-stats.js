var Plots = {
    timeplot_id: '',
    timeplotCount: 0,
    timeplot: null,
    currentCSV: '',
    csvSources: {},
    timeGeometryConfig: {
        gridColor: '#000000',
        axisLabelsPlacement: 'top'
    },
    valueGeometryConfig: {
        gridColor: '#000000',
        axisLabelsPlacement: 'left',
        min: 0
    },
    plotInfoCommonConfig: {
        showValues: true,
        dotRadius: 2.0,
        lineWidth: 2.0
    },
    plotInfoConfigs: {},
    defined: [
        { name: 'addons_downloaded', column: 1 },
        { name: 'addons_in_use', column: 2 },
        { name: 'addons_created', column: 3 },
        { name: 'addons_updated', column: 4 },
        { name: 'users_created', column: 5 },
        { name: 'reviews_created', column: 6 },
        { name: 'collections_created', column: 7 }
    ],

    initialize: function() {
        this.csvSources = {
            date: statsURL + 'sitecsv/date',
            week: statsURL + 'sitecsv/week',
            month: statsURL + 'sitecsv/month'
        };

        var that = this;
        $.each(this.defined, function(i, plot) {
            var plotColor = colors.getNext();
            that.plotInfoConfigs[plot.name] = {
                id: 'plot-'+plot.name,
                dotColor: plotColor,
                lineColor: plotColor,
                fillColor: plotColor,
                column: plot.column
            };
        });
    },

    newTimeplot: function() {
        if (this.timeplot_id != '') {
            $('#' + this.timeplot_id).remove();
            this.timeplot = null;
        }
        this.timeplotCount++;
        
        $('#not-enough-data').hide();
        
        this.timeplot_id = 'timeplot' + this.timeplotCount;
        $('#timeplot-container').append('<div id="' + this.timeplot_id + '" class="timeplot"></div>');
    },

    plot: function(metric, groupBy, pointCount) {
        if (!(metric in this.plotInfoConfigs) || !(groupBy in this.csvSources) || (pointCount < 1)) {
            return;
        }

        // determine the starting date of the plot
        var today = new Date();
        var minDate = new Date();
        if (groupBy == 'date') {
            minDate.setDate(today.getDate() - (pointCount - 1));

        } else if (groupBy == 'week') {
            minDate.setDate(today.getDate() - today.getDay() - (7 * (pointCount - 1)));

        } else if (groupBy == 'month') {
            minDate.setDate(1);
            minDate.setMonth(today.getMonth() - (pointCount - 1));
        }

        // customize time geometry config
        var timeGeometryConfig = {};
        $.extend(timeGeometryConfig, this.timeGeometryConfig, {
            min: minDate,
            max: today
        });

        // start with a fresh eventsource 
        var eventSource = new Timeplot.DefaultEventSource();

        // since the table shows all metrics, only update it when the data source changes
        if (this.dataTable && this.csvSources[groupBy] != this.currentCSV) {
            this.dataTable.listenTo(eventSource);
            this.dataTable.setDownloadLink(this.csvSources[groupBy]);
        }
        this.currentCSV = this.csvSources[groupBy];

        // instantiate plotinfo
        var plotConfig = {};
        $.extend(plotConfig, this.plotInfoCommonConfig, this.plotInfoConfigs[metric], {
            timeGeometry: new Timeplot.DefaultTimeGeometry(timeGeometryConfig),
            valueGeometry: new Timeplot.DefaultValueGeometry(this.valueGeometryConfig),
            dataSource: new Timeplot.ColumnSource(eventSource, this.plotInfoConfigs[metric].column)
        });
        var plotInfo = Timeplot.createPlotInfo(plotConfig);

        // build the new timeplot
        this.newTimeplot();
        this.timeplot = Timeplot.create(document.getElementById(this.timeplot_id), [plotInfo]);
        this.timeplot.loadText(this.currentCSV, ',', eventSource);
    }
};

var plotSelection = {
    dropdowns: {},

    initialize: function() {
        // plot selection
        var plotDropdown = new Dropdown({
                'id': 'plot-selector',
                'type': 'big-menu wide-menu',
                'onChange': 'plotSelection.updatePlot();',
                'hasColorbox': false,
                'parentDOM': 'plot-selector-area',
                'removeOnNewTimeplot': false
            });
        this.dropdowns[plotDropdown.config.id] = plotDropdown;

        var menu = plotDropdown.addMenu({
                'name': 'menu',
                'showNoneForLevel1': false
        });

        $.each(Plots.defined, function(i, plot) {
            var item = menu.addItem({ name: localized[plot.name], value: plot.name });
            if (i == 0) {
                item.select();
            }
        });
        
        // groupby selection
        var groupbyDropdown = new Dropdown({
                'id': 'groupby-selector',
                'type': 'group-by',
                'onChange': 'plotSelection.groupbyChanged(this);',
                'hasColorbox': false,
                'parentDOM': 'plot-selection',
                'removeOnNewTimeplot': false
            });
        this.dropdowns[groupbyDropdown.config.id] = groupbyDropdown;

        menu = groupbyDropdown.addMenu({
                'name': 'menu',
                'showNoneForLevel1': false
        });

        menu.addItem({'value': 'date',  'name': localized['statistics_js_groupby_selector_date']}).select();
        menu.addItem({'value': 'week',  'name': localized['statistics_js_groupby_selector_week']});
        menu.addItem({'value': 'month', 'name': localized['statistics_js_groupby_selector_month']});
        
        // daily count selection
        var countDropdown = new Dropdown({
                'id': 'day-count-selector',
                'type': 'wide-menu',
                'onChange': 'plotSelection.updatePlot();',
                'hasColorbox': false,
                'parentDOM': 'plot-selection',
                'removeOnNewTimeplot': false
            });
        this.dropdowns[countDropdown.config.id] = countDropdown;

        menu = countDropdown.addMenu({
                'name': 'menu',
                'showNoneForLevel1': false
        });

        menu.addItem({ value: 30, name: localized['statistics_js_last_30days'] }).select();
        menu.addItem({ value: 60, name: localized['statistics_js_last_60days'] });
        menu.addItem({ value: 90, name: localized['statistics_js_last_90days'] });

        // weekly count selection
        countDropdown = new Dropdown({
                'id': 'week-count-selector',
                'type': 'wide-menu',
                'onChange': 'plotSelection.updatePlot();',
                'hasColorbox': false,
                'parentDOM': 'plot-selection',
                'removeOnNewTimeplot': false
            });
        this.dropdowns[countDropdown.config.id] = countDropdown;

        menu = countDropdown.addMenu({
                'name': 'menu',
                'showNoneForLevel1': false
        });

        menu.addItem({ value: 18, name: localized['statistics_js_last_18weeks'] });
        menu.addItem({ value: 36, name: localized['statistics_js_last_36weeks'] });
        menu.addItem({ value: 54, name: localized['statistics_js_last_54weeks'] });
        
        $('#week-count-selector').hide();

        // monthly count selection
        countDropdown = new Dropdown({
                'id': 'month-count-selector',
                'type': 'wide-menu',
                'onChange': 'plotSelection.updatePlot();',
                'hasColorbox': false,
                'parentDOM': 'plot-selection',
                'removeOnNewTimeplot': false
            });
        this.dropdowns[countDropdown.config.id] = countDropdown;

        menu = countDropdown.addMenu({
                'name': 'menu',
                'showNoneForLevel1': false
        });

        menu.addItem({ value: 12, name: localized['statistics_js_last_12months'] });
        menu.addItem({ value: 24, name: localized['statistics_js_last_24months'] });
        menu.addItem({ value: 36, name: localized['statistics_js_last_36months'] });

        $('#month-count-selector').hide();
    },
    
    groupbyChanged: function(dropdown) {
        var groupby = (dropdown.selectedItem ? dropdown.selectedItem.value : null);

        // hide/show appropriate count menus, selecting the first item if none selected
        if (groupby == 'date') {
            $('#week-count-selector, #month-count-selector').hide();
            $('#day-count-selector').show();
            if ('day-count-selector' in this.dropdowns && ! this.dropdowns['day-count-selector'].selectedItem) {
                $('#day-count-selector li.plot-item a:first').click();
                return;
            }

        } else if (groupby == 'week') {
            $('#day-count-selector, #month-count-selector').hide();
            $('#week-count-selector').show();
            if ('week-count-selector' in this.dropdowns && ! this.dropdowns['week-count-selector'].selectedItem) {
                $('#week-count-selector li.plot-item a:first').click();
                return;
            }

        } else if (groupby == 'month') {
            $('#day-count-selector, #week-count-selector').hide();
            $('#month-count-selector').show();
            if ('month-count-selector' in this.dropdowns && ! this.dropdowns['month-count-selector'].selectedItem) {
                $('#month-count-selector li.plot-item a:first').click();
                return;
            }
        }

        this.updatePlot();
    },

    updatePlot: function() {
        var plot = this.getSelected('plot-selector');
        var groupby = this.getSelected('groupby-selector');
        var count = this.getSelected(['day-count-selector', 'week-count-selector', 'month-count-selector']);

        if (plot && groupby && count) {
            Plots.plot(plot, groupby, count);
        }
    },

    /**
     * Return the value of the selected item in a visible dropdown (or null if none selected or visible)
     *
     * @param idOrList string or list of id strings (in which case the first selected value is returned)
     */
    getSelected: function(idOrList) {
        if (typeof idOrList == 'string') {
            idOrList = [idOrList];
        }

        for (var i in idOrList) {
            var id = idOrList[i];
            if (id in this.dropdowns && $('#'+idOrList[i]).is(':visible') && this.dropdowns[id].selectedItem) {
                return this.dropdowns[id].selectedItem.value;
            }
        }
        return null;
    }
};
