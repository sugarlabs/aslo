var Plots = {
    timeplot_id: '',
    timeplotCount: 0,
    timeplot: null,
    availableFields: [],
    currentCSV: '',
    dataTable: null,

    initialize: function() {
        // setup handler for contributions data table grouping
        $('#contributions-group-by').change(this.contributionsGroupByChange);
    },
    
    determinePlot: function() {
        var selected = plotSelection.dropdowns['plot-selector'].selectedItem.value;
        var group_by = plotSelection.getGroupByValue();

        // Remove current plot selections
        plotSelection.removeAll();
        
        // Make sure summary-specific options are shown or hidden as approp.
        this.summary.updateOptionsVisibility();
        
        if (selected == 'summary')
            this.summary.initialize();
        else if (selected == 'custom')
            Plots.customPlot();
        else
            Plots.defined.initialize();
    },
    
    newTimeplot: function() {
        if (this.timeplot_id != '') {
            $('#' + this.timeplot_id).remove();
            this.timeplot = null;
        }
        this.timeplotCount++;
        
        $('#not-enough-data').hide();
        $('#no-contributions').hide();

        if (this.dataTable) {
            $('#stats-table-listing').hide();
            this.dataTable.clearTable();
        }
        
        this.timeplot_id = 'timeplot' + this.timeplotCount;
        $('#timeplot-container').append('<div id="' + this.timeplot_id + '" class="timeplot"></div>');
    },
    
    summary: {
        dataSources: {},
        timeGeometry: {},
        valueGeometry: {},
        plotInfo: {},
        zoomLevel: 0,
        
        initialize: function() {       
            this.dataSources = {
                'downloads': new Timeplot.DefaultEventSource(),
                'updatepings': new Timeplot.DefaultEventSource()
            };

            this.valueGeometry = {
                'downloads': {
                    'gridColor': '#000000',
                    'axisLabelsPlacement': 'left'
                },
                'updatepings': {
                    'gridColor': '#9966CC',
                    'axisLabelsPlacement': 'right'
                }
            };
            
            this.timeGeometry = {
                'gridColor': '#000000',
                'axisLabelsPlacement': 'bottom',
                'min': this.getInitialMinDate()
            };
            
            this.plotInfo = {
                'downloads': {
                    'id': 'downloads',
                    'dataSource': new Timeplot.ColumnSource(this.dataSources['downloads'], 1),
                    'valueGeometry': new Timeplot.DefaultValueGeometry(this.valueGeometry['downloads']),
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat,
                    'dotColor': '#33AAFF',
                    'dotRadius': 3.0,
                    'lineColor': '#33AAFF',
                    'lineWidth': 3.0
                },
                'updatepings': {
                    'id': 'updatepings',
                    'dataSource': new Timeplot.ColumnSource(this.dataSources['updatepings'], 1),
                    'valueGeometry': new Timeplot.DefaultValueGeometry(this.valueGeometry['updatepings']),
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat,
                    'dotColor': '#EE3322',
                    'dotRadius': 3.0,
                    'lineColor': '#EE3322',
                    'lineWidth': 3.0
                }
            };
            
            this.plot();
            this.zoomLevel = 0;
            this.updateButtonVisibility();
        },
        
        plot: function() {
            var timeGeometry = new Timeplot.DefaultTimeGeometry(this.timeGeometry);
            
            this.plotInfo['downloads'].timeGeometry = timeGeometry;
            this.plotInfo['updatepings'].timeGeometry = timeGeometry;
            
            var plotInfo = [
                Timeplot.createPlotInfo(this.plotInfo['downloads']),
                Timeplot.createPlotInfo(this.plotInfo['updatepings'])
            ];
            
            Plots.newTimeplot();
            Plots.timeplot = Timeplot.create(document.getElementById(Plots.timeplot_id), plotInfo);
            Plots.timeplot.loadText(statsURL + 'csv/' + addonID + '/downloads', ",", this.dataSources['downloads']);
            Plots.timeplot.loadText(statsURL + 'csv/' + addonID + '/updatepings', ",", this.dataSources['updatepings']);
        },
        
        zoomIn: function() {
            this.zoomLevel++;
            this.updateButtonVisibility();
            
            var date = this.timeGeometry.min;
            var matches = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
            
            if (matches[2] != 12)
                var newDate = matches[1] + '-' + this.leadingZero(parseInt(matches[2]) + 1) + '-01';
            else
                var newDate = (parseInt(matches[1]) + 1) + '-01-01'
            
            this.timeGeometry.min = newDate;
            
            this.plot();
        },
        
        zoomOut: function() {
            this.zoomLevel--;
            this.updateButtonVisibility();
            
            var date = this.timeGeometry.min;
            var matches = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
            
            if (matches[2] != 1)
                var newDate = matches[1] + '-' + this.leadingZero(parseInt(matches[2]) - 1) + '-01';
            else
                var newDate = (parseInt(matches[1]) - 1) + '-12-01'
            
            this.timeGeometry.min = newDate;
            this.updateDotRadius();
            
            this.plot();
        },
        
        updateOptionsVisibility: function() {
            var plotType = plotSelection.dropdowns['plot-selector'].selectedItem.value;
            
            if (plotType == 'summary') {
                $('#weeks-legend').hide();
                $('#summary-legend').show();
                $('#summary-options').show();
                $('#group-by-selector').hide();
                $('#stats_contributions_overview').hide();
                $('#stats-table-listing .listing-header').hide();
                $('#stats_overview').show();
            }
            else if (plotType == 'contributions') {
                $('#weeks-legend').hide();
                $('#summary-legend').hide();
                $('#summary-options').hide();
                $('#group-by-selector').hide();
                $('#stats_contributions_overview').show();
                $('#stats-table-listing .listing-header').show();
                $('#stats_overview').hide();
            }
            else {
                $('#weeks-legend').hide();
                $('#summary-legend').hide();
                $('#summary-options').hide();
                $('#group-by-selector').show();
                $('#stats_contributions_overview').hide();
                $('#stats-table-listing .listing-header').hide();
                $('#stats_overview').show();
            }
        },
        
        updateButtonVisibility: function() {
            if (this.zoomLevel >= 0)
                $('#zoom-in').addClass('disabled');
            else
                $('#zoom-in').removeClass('disabled');
        },
        
        updateDotRadius: function() {
            if (this.zoomLevel >= -1 && this.zoomLevel <= 1) {
                this.plotInfo['downloads'].dotRadius = 3.0;
                this.plotInfo['updatepings'].dotRadius = 3.0;
            }
            else if (this.zoomLevel >= -3 && this.zoomLevel <= 3) {
                this.plotInfo['downloads'].dotRadius = 2.0;
                this.plotInfo['updatepings'].dotRadius = 2.0;
            }
            else {
                this.plotInfo['downloads'].dotRadius = 1.0;
                this.plotInfo['updatepings'].dotRadius = 1.0;
            }
        },
        
        getInitialMinDate: function() {
            var currentTime = new Date();
            var year = currentTime.getFullYear();
            var month = currentTime.getMonth() + 1;
            var day = currentTime.getDate();
            
            if (day < 15) {
                if (month == 1) {
                    month = 12;
                    year--;
                }
                else
                    month--;
                day = 15;
            }
            else if (day >= 15) {
                day = 1;
            }
            
            var date = year + '-' + this.leadingZero(month) + '-' + this.leadingZero(day);
            
            return date;
            
        },
        
        leadingZero: function(number) {
            return (number < 10 ? '0' + number : number);
        }
    },
    
    defined: {
        dataSources: {},
        timeGeometry: {},
        valueGeometry: {},
        plotInfo: {},
        
        initialize: function() {
            var type = plotSelection.dropdowns['plot-selector'].selectedItem.value;
            Plots.currentCSV = statsURL + 'csv/' + addonID + '/' + type;

            // If there's a value for the group-by selector, add it as a
            // parameter to the CSV URL to invoke it server-side.
            var group_by = plotSelection.getGroupByValue();

            if ('week_over_week' == group_by) {
                if ('updatepings' == type || 'downloads' == type) {
                    $('#weeks-legend').show();
                    return this.plotWeekOverWeek();
                } else {
                    // HACK: Until I can figure out how to disable the Compare
                    // by: Week option for views not supported for it
                    group_by = 'week';
                }
            } 

            $('#weeks-legend').hide();
            if (group_by) Plots.currentCSV += '?group_by=' + group_by;

            this.dataSources = {
                'count': new Timeplot.DefaultEventSource(),
                'events-firefox': new Timeplot.DefaultEventSource(),
                'events-addon': new Timeplot.DefaultEventSource()
            };

            this.valueGeometry = {
                'gridColor': '#000000',
                'axisLabelsPlacement': 'left',
                'min': 0
            };
            
            var min = '2007-07-01';
            if (type == 'contributions' && plotSelection.summary.contributions.availableDates)
                min = plotSelection.summary.contributions.availableDates[0];
            else if (type == 'downloads' && plotSelection.summary.downloads.availableDates)
                min = plotSelection.summary.downloads.availableDates[0];
            else if (plotSelection.summary.updatepings.availableDates)
                min = plotSelection.summary.updatepings.availableDates[0];
            
            this.timeGeometry = {
                'gridColor': '#000000',
                'axisLabelsPlacement': 'top',
                'min': min
            };
            
            this.plotInfo = {
                'count': {
                    'id': 'count',
                    'dataSource': new Timeplot.ColumnSource(this.dataSources['count'], 1),
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat,
                    'dotColor': '#000000',
                    'lineColor': '#000000',
                    'fillColor': '#000000'
                },
                'events-firefox': {
                    'id': 'events-firefox',
                    'eventSource': this.dataSources['events-firefox'],
                    'lineColor': '#000000'
                },
                'events-addon': {
                    'id': 'events-addon',
                    'eventSource': this.dataSources['events-addon'],
                    'lineColor': '#000000'
                },
                'default': {
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat
                }
            };

            if (type == 'downloads' || type == 'updatepings' || type == 'contributions') {
                if (type == 'downloads') {
                    var dark = '#3366CC';
                    var light = '#3399CC';
                    this.plotInfo['count'].dotColor = null;
                    this.plotInfo['count'].fillGradient = false;
                }
                else if (type == 'updatepings') {
                    var dark = '#9966CC';
                    var light = '#CC99CC';
                    this.plotInfo['count'].dotColor = null;
                    this.plotInfo['count'].fillGradient = false;
                }
                else if (type == 'contributions') {
                    var dark = '#009933';
                    var light = '#339933';
                    this.plotInfo['count'].valueFormatter = dollarFormat;
                    this.plotInfo['count'].lineColor = dark;
                    this.plotInfo['count'].dotColor = dark;
                    this.plotInfo['count'].fillGradient = true;

                    // reset group-by dropdown
                    $('#contributions-group-by option:selected').removeAttr('selected');
                    $("#contributions-group-by option[value='date']").attr('selected', 'selected');
                }
                
                this.plotInfo['count'].fillColor = dark;
                this.plotInfo['events-firefox'].lineColor = light;
                this.plotInfo['events-addon'].lineColor = light;
            }
            
            this.plot();
        },

        plot: function() {
            var timeGeometry = new Timeplot.DefaultTimeGeometry(this.timeGeometry);
            var valueGeometry = new Timeplot.DefaultValueGeometry(this.valueGeometry);
            
            this.plotInfo['count'].timeGeometry = timeGeometry;
            this.plotInfo['count'].valueGeometry = valueGeometry;
            this.plotInfo['events-firefox'].timeGeometry = timeGeometry;
            this.plotInfo['events-addon'].timeGeometry = timeGeometry;
            this.plotInfo['default'].timeGeometry = timeGeometry;
            this.plotInfo['default'].valueGeometry = valueGeometry;
            
            var plotInfo = [
                Timeplot.createPlotInfo(this.plotInfo['count']),
                Timeplot.createPlotInfo(this.plotInfo['events-firefox']),
                Timeplot.createPlotInfo(this.plotInfo['events-addon'])
            ];

            Plots.newTimeplot();
            if (Plots.dataTable) {
                var plotType = plotSelection.dropdowns['plot-selector'].selectedItem.value;
                if (plotType == 'contributions') {
                    Plots.dataTable.config['valueFormatters'] = [null, dollarFormat, null, dollarFormat];
                } else {
                    Plots.dataTable.config['valueFormatters'] = [null, this.plotInfo['count'].valueFormatter];
                }

                $('#stats-table-listing').show();
                Plots.dataTable.listenTo(this.dataSources['count']);
                Plots.dataTable.setDownloadLink(Plots.currentCSV);
            }

            Plots.timeplot = Timeplot.create(document.getElementById(Plots.timeplot_id), plotInfo);
            Plots.timeplot.loadText(Plots.currentCSV, ",", this.dataSources['count'], Plots.parseFields);
            Plots.timeplot.loadXML(statsURL + 'xml/events/firefox', this.dataSources['events-firefox']);
            Plots.timeplot.loadXML(statsURL + 'xml/events/addon/' + addonID, this.dataSources['events-addon']);
        },

        /**
         * Construct a plot consisting of 2 different weeks' data overlaid on
         * the same graph.
         */
        plotWeekOverWeek: function() {

            var type = plotSelection.dropdowns['plot-selector'].selectedItem.value;
            Plots.currentCSV = statsURL + 'csv/' + addonID + '/' + type;

            this.dataSources = {
                'count': new Timeplot.DefaultEventSource()
            };

            var date_parser = Timeline.NativeDateUnit.getParser('iso8601');

            // Collect Mondays from the set of available dates.
            var available_dates = plotSelection
                .summary[ (type=='downloads') ? 'downloads' : 'updatepings' ]
                .availableDates;
            var dates = [];
            var DAY_MONDAY = 1;
            for (idx in available_dates) {
                // The available dates data is a little weird - it's an object
                // with numerical properties, instead of an array.
                if (available_dates.hasOwnProperty(idx)) {
                    var date = date_parser(available_dates[idx]);
                    if (date.getDay() == DAY_MONDAY)
                        dates.push(available_dates[idx]);
                }
            }

            // Set up empty ranges set.
            var ranges = {
                week1: { min: '', max: '' },
                week2: { min: '', max: '' }
            };

            var WEEK = ( 1000 * 60 * 60 * 24 * 7 );

            if ( $('#weeks-legend .template').length ) {

                // The date selection dropdowns still contain unpopulated
                // templates, so set up the defaults and populate the dropdowns
                
                ranges['week2']['min'] = date_parser(dates[dates.length - 1]);
                ranges['week2']['max'] = new Date(ranges['week2']['min'].getTime() + WEEK);
                ranges['week1']['min'] = date_parser(dates[dates.length - 2]);
                ranges['week1']['max'] = new Date(ranges['week1']['min'].getTime() + WEEK);

                var weeks = ['week1', 'week2'];
                for (var i=0, week; week=weeks[i]; i++) {

                    // Convert the selection template into a concrete element
                    var tmpl = $('#'+week+'-selection select.template');
                    if (!tmpl.length) continue;
                    tmpl.remove().removeClass('template');

                    // Convert the current min date to string for comparison.
                    var min_str = ranges[week]['min'].strftime('%Y-%m-%d');

                    // Now populate the selection element with options cloned
                    // and populated from available dates.
                    var t_opt = tmpl.find('option').remove();
                    for (var j=0,date; date=dates[j]; j++) {
                        var opt = t_opt.clone();

                        var dp = date.split('-');
                        opt.text([dp[1], dp[2], dp[0]].join('/'))
                            .attr('value', date)
                            .appendTo(tmpl);

                        if (date == min_str)
                            opt.attr('selected', 'selected');
                    }

                    // Finally inject the populated selector into the DOM and
                    // wire up an onChange handler to reload the plot.
                    tmpl.appendTo('#'+week+'-selection')
                        .change(function() { Plots.determinePlot() });
                }

            } else {

                // The date selection dropdowns are populated, so set ranges
                // from the selected values.
                
                var weeks = ['week1', 'week2'];
                for (var i=0, week; week=weeks[i]; i++) {
                    var opt = $('#'+week+'-selection select')[0];
                    var date = opt.options[opt.selectedIndex].value;
                    ranges[week]['min'] = date_parser(date);
                    ranges[week]['max'] = new Date(ranges[week]['min'].getTime() + WEEK);
                }

            }

            // Assemble the time geometries from the selected time ranges.
            this.timeGeometries = {
                'week1': {
                    'min': ranges['week1']['min'],
                    'max': ranges['week1']['max'],
                    'gridColor': '#CC6666',
                    'axisLabelsPlacement': 'bottom'
                },
                'week2': {
                    'min': ranges['week2']['min'],
                    'max': ranges['week2']['max'],
                    'gridColor': '#6666CC',
                    'axisLabelsPlacement': 'top'
                }
            };

            // Both time ranges share the same value geometry
            this.valueGeometry = {
                'gridColor': '#000000',
                'axisLabelsPlacement': 'left',
                'min': 0
            };

            // Construct the plot info structures for each of the overlaid sets.
            Plots.newTimeplot();
            Plots.timeplot = Timeplot.create(document.getElementById(Plots.timeplot_id), [
                Timeplot.createPlotInfo({
                    'id': 'week1-plot',
                    'dataSource':    
                        new Timeplot.ColumnSource(this.dataSources['count'], 1),
                    'timeGeometry':  
                        new Timeplot.DefaultTimeGeometry(this.timeGeometries['week1']),
                    'valueGeometry': 
                        new Timeplot.DefaultValueGeometry(this.valueGeometry),
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat,
                    'dotColor':   '#CC6666',
                    'dotRadius':  3.0,
                    'lineColor':  '#CC6666',
                    'lineWidth':  3.0,
                    'fillColor':  false
                }),
                Timeplot.createPlotInfo({
                    'id': 'week2-plot',
                    'dataSource':    
                        new Timeplot.ColumnSource(this.dataSources['count'], 1),
                    'timeGeometry':  
                        new Timeplot.DefaultTimeGeometry(this.timeGeometries['week2']),
                    'valueGeometry': 
                        new Timeplot.DefaultValueGeometry(this.valueGeometry),
                    'showValues': true,
                    'roundValues': false,
                    'valueFormatter': numberFormat,
                    'dotColor':   '#6666CC',
                    'dotRadius':  3.0,
                    'lineColor':  '#6666CC',
                    'lineWidth':  3.0,
                    'fillColor':  false
                })
            ]);

            // Finally, queue up a load for the plot data.
            Plots.timeplot.loadText(
                Plots.currentCSV, 
                ",", 
                this.dataSources['count'], 
                Plots.parseFields
            );

        },
        
        addPlot: function(plotName, column, color, formatter) {
            if (plotName in this.plotInfo)
                var plotInfo = this.plotInfo[plotName];
            else {
                var plotInfo = this.plotInfo['default'];
                plotInfo.id = plotName;
                plotInfo.dataSource = new Timeplot.ColumnSource(this.dataSources['count'], column);
                plotInfo.dotColor = color;
                plotInfo.lineColor = color;
                plotInfo.fillColor = color;
                plotInfo.valueFormatter = (typeof formatter == 'function' ? formatter : numberFormat);
            }
            
            return Plots.timeplot.addPlot(Timeplot.createPlotInfo(plotInfo), true);
        },
        
        addEventPlot: function(plotName) {
            return Plots.timeplot.addPlot(Timeplot.createPlotInfo(this.plotInfo[plotName]), true);
        },
        
        removePlot: function(plotName) {
            return Plots.timeplot.removePlot(plotName);
        }
    },
    
    customPlot: function() {
        plotSelection.addCustomDropdown();
    },
    
    resizePlot: function(newHeight) {
        $('#' + Plots.timeplot_id).height(newHeight);
        Plots.timeplot.repaint();
    },

    parseFields: function(data) {
        if (data != '') {
            var rawFields = parseRawFields(data);

            if (rawFields.length > 0) {
                // Enough data
                $('#not-enough-data').hide();
                $('#no-contributions').hide();
                
                Plots.availableFields = rawFields;
                plotSelection.addDefinedDropdowns();

                if (Plots.dataTable) {
                    var headers = Plots.availableFields;
                    $.each(headers, function (i, val) {
                        // Try to pretty up headers that look like app GUIDs
                        if (val.substr(0,1) == '{') {
                            headers[i] = plotSelection.getApplicationName(val)['itemName'];
                        }
                    });
                    Plots.dataTable.setHeaders(headers);
                }
            }
            else {
                // Not enough data

                var plotType = plotSelection.dropdowns['plot-selector'].selectedItem.value;
                if (plotType == 'contributions') {
                    $('#no-contributions').show();
                } else {
                    $('#not-enough-data').show();
                }
                
                if (Plots.timeplot_id != '') {
                    $('#' + Plots.timeplot_id).remove();
                    Plots.timeplot = null;
                }
            }
        }
        
        return data;
    },

    contributionsGroupByChange: function() {
        // this = select node
        var groupBy = $('option:selected', this).val();
        var url = statsURL+'csv/'+addonID+'/contributions?group_by='+groupBy;
        var eventSource = new Timeplot.DefaultEventSource();

        Plots.dataTable.clearTable();
        Plots.dataTable.listenTo(eventSource);
        Plots.dataTable.setDownloadLink(url);
        if (groupBy == 'transaction') {
            Plots.dataTable.config['valueFormatters'] = [null, dollarFormat, dollarFormat];
        } else {
            Plots.dataTable.config['valueFormatters'] = [null, dollarFormat, null, dollarFormat];
        }

        $.ajax({
            url: url,
            dataType: 'text',
            success: function(data, textStatus) {
                // this = ajax options for this request
                try {
                    eventSource.loadText(data, ',', this.url, function(text){
                        Plots.dataTable.setHeaders(parseRawFields(text));
                        return text;
                    });
                } catch (e) {
                    SimileAjax.Debug.exception(e);
                }
            },
            error: function(request, textStatus, errorThrown) {
                // this = ajax options for this request
            }
        });
    }
};
    
