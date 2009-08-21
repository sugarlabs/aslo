var Plots = {
    timeplots: {
        subscribers: {
            id: '',
            timeplot: null
        },
        ratings: {
            id: '',
            timeplot: null
        },
        downloads: {
            id: '',
            timeplot: null
        }
    },
    timeplotCount: 0,
    daysToShow: 7,
    currentCSV: '',
    eventSourceListener: null,
    timeGeometryConfig: {
        gridColor: '#000000',
        axisLabelsPlacement: 'bottom'
    },
    valueGeometryConfig: {
        gridColor: '#000000',
        axisLabelsPlacement: 'right',
        min: 0
    },

    initialize: function() {
       this.plot();
    },

    newTimeplots: function() {
        $('#not-enough-data').hide();

        var that = this;
        $.each(this.timeplots, function(key, plot) {
            if (plot.id != '') {
                $('#' + plot.id).remove();
                plot.timeplot = null;
            }
            that.timeplotCount++;
            plot.id = 'timeplot' + that.timeplotCount;
            $('#timeplot-container-' + key).append('<div id="' + plot.id+ '" class="timeplot"></div>');
        });
    },

    plot: function() {

        // determine the starting date of the plot
        var today = new Date();
        var minDate = new Date();
        minDate.setDate(today.getDate() - (this.daysToShow-1));

        // customize time geometry config
        var timeGeometryConfig = {};
        $.extend(timeGeometryConfig, this.timeGeometryConfig, {
            min: minDate.strftime('%Y-%m-%d'),
            max: today.strftime('%Y-%m-%d')
        });

        // all plots share the same time geometry
        var timeGeometry = new Timeplot.DefaultTimeGeometry(timeGeometryConfig);

        // wipe out any old timeplots
        this.newTimeplots();

        // start with a fresh eventsource 
        var eventSource = new Timeplot.DefaultEventSource();

        // setup any listener
        if (this.eventSourceListener && (typeof this.eventSourceListener.listenTo == 'function')) {
            this.eventSourceListener.listenTo(eventSource);
        }

        // subscribers plot
        var valueGeometry = new Timeplot.DefaultValueGeometry(this.valueGeometryConfig);
        var plotInfo = [
            Timeplot.createPlotInfo({
                id: 'plot-subscribers',
                dotColor: '#b5d9e5',
                lineColor: '#b5d9e5',
                fillColor: '#daeef5',
                showValues: true,
                dotRadius: 2.0,
                lineWidth: 2.0,
                timeGeometry: timeGeometry,
                valueGeometry: valueGeometry,
                dataSource: new Timeplot.ColumnSource(eventSource, 1)
            })
        ];
        this.timeplots.subscribers.timeplot = Timeplot.create(document.getElementById(this.timeplots.subscribers.id), plotInfo);

        // ratings plot
        valueGeometry = new Timeplot.DefaultValueGeometry(this.valueGeometryConfig);
        plotInfo = [
            Timeplot.createPlotInfo({
                id: 'plot-ratings-up',
                dotColor: '#b5d9e5',
                lineColor: '#b5d9e5',
                fillColor: '#daeef5',
                showValues: true,
                dotRadius: 2.0,
                lineWidth: 2.0,
                timeGeometry: timeGeometry,
                valueGeometry: valueGeometry,
                dataSource: new Timeplot.ColumnSource(eventSource, 2)
            }),
            Timeplot.createPlotInfo({
                id: 'plot-ratings-down',
                dotColor: '#e4bfb4',
                lineColor: '#e4bfb4',
                showValues: true,
                dotRadius: 2.0,
                lineWidth: 2.0,
                timeGeometry: timeGeometry,
                valueGeometry: valueGeometry,
                dataSource: new Timeplot.ColumnSource(eventSource, 3)
            })
        ];
        this.timeplots.ratings.timeplot = Timeplot.create(document.getElementById(this.timeplots.ratings.id), plotInfo);

        // downloads plot
        valueGeometry = new Timeplot.DefaultValueGeometry(this.valueGeometryConfig);
        plotInfo = [
            Timeplot.createPlotInfo({
                id: 'plot-downloads',
                dotColor: '#b5d9e5',
                lineColor: '#b5d9e5',
                fillColor: '#daeef5',
                showValues: true,
                dotRadius: 2.0,
                lineWidth: 2.0,
                timeGeometry: timeGeometry,
                valueGeometry: valueGeometry,
                dataSource: new Timeplot.ColumnSource(eventSource, 4)
            })
        ];
        this.timeplots.downloads.timeplot = Timeplot.create(document.getElementById(this.timeplots.downloads.id), plotInfo);

        // Load the data (which triggers all listening plots to update)
        this.timeplots.subscribers.timeplot.loadText(this.currentCSV, ',', eventSource);
    }
};
 
var CollectionStats = {
    addon_data: null,

    initialize: function() {
        // Initialize Plots
        var uuid = $('#collection-uuid option:selected:first').attr('value');

        Plots.currentCSV = statsURL + 'collectioncsv/' + uuid;
        Plots.daysToShow = this.daysForPeriod();
        Plots.eventSourceListener = this;
        Plots.initialize();

        if (uuid) {
            this.loadAddonData(uuid);
        }

        // Take over period change links in order to load just data and not the entire page
        $('#period-week a').bind( 'click', { period:'week'  }, this.changePeriod);
        $('#period-month a').bind('click', { period:'month' }, this.changePeriod);
        $('#period-year a').bind( 'click', { period:'year'  }, this.changePeriod);

        // Generate addon download comparison bars
        $('.download-bullet').removeClass('hidden').show();
        $.sparkline_display_visible();
        $('.download-bullet').sparkline('html', {
            type: 'bullet',
            width: '100%',
            performanceColor: 'rgb(200,232,243)',
            targetColor: 'rgba(200,232,243,0)',
            targetWidth: 2
        });
    },

    loadAddonData: function(uuid) {
        var url = statsURL + 'collectionjson/' + uuid;
        var that = this;
        $.getJSON(url, function(json, textStatus) {
            that.addon_data = json.addon_data;
            that.plotAddonSparkline();
        });
    },

    plotAddonSparkline: function() {
        if (! this.addon_data) {
            return;
        }

        var nDays = this.daysForPeriod();
        $.each(this.addon_data, function(addon_id, data) {
            var csv = data.slice(Math.max(0, data.length-nDays)).join(',');
            $('#addon-item'+addon_id+' div.download-line').text(csv);
        });

        $('.download-line').sparkline('html', {
            type: 'line',
            width: '100%',
            spotColor: false,
            minSpotColor: false,
            maxSpotColor: false,
            lineColor: '#b5d9e5',
            fillColor: '#daeef5'
        });
    },

    getPeriod: function() {
        return $('#plot-period').attr('value');
    },

    setPeriod: function(newPeriod) {
        $('#plot-period').attr('value', newPeriod);
    },

    daysForPeriod: function(period) {
        if (typeof period == 'undefined') {
            var period = CollectionStats.getPeriod();
        }

        var map = {
            week: 7,
            month: 31,
            year: 365
        };
        if (period in map) {
            return map[period];
        }
        return map['week']; // so an invalid period does not completely break plots
    },

    // callback for changing plot period/timerange
    changePeriod: function(e) {
        var period = e.data.period;

        // update page nav elements to reflect new period
        $('#period-week, #period-month, #period-year').removeClass('selected');
        $('#period-' + period).addClass('selected');
        CollectionStats.setPeriod(period)

        // reload plots with new timerange
        Plots.daysToShow = CollectionStats.daysForPeriod(period);
        Plots.plot();

        // redraw download sparklines
        CollectionStats.plotAddonSparkline();

        return false; // cancel the clicked link request
    },

    // Register self to listen to a timeplot event source
    listenTo: function(eventSource) {
        var that = this;
        eventSource.addListener({
            onAddMany: function() {
                return that.onAddMany(eventSource);
            }
        });
    },

    // Timeplot listener hook: total various metrics over selected period
    onAddMany: function(source) {
        var nDays = this.daysForPeriod();

        // Convert all the events into an array of arrays.
        var data_rows = [],
            evt, i_evt = source.getAllEventIterator();
        while (evt = i_evt.next()) {
            data_rows.push(
                [ evt.getTime() ].concat( evt.getValues() )
            );
        }

        // sum the metrics over the last nDays
        var subscriberSum = 0, ratingUpSum = 0, ratingDownSum = 0, downloadSum = 0;
        for (var i = Math.max(0, data_rows.length - nDays); i < data_rows.length; i++) {
            subscriberSum += parseInt(data_rows[i][1]);
            ratingUpSum += parseInt(data_rows[i][2]);
            ratingDownSum += parseInt(data_rows[i][3]);
            downloadSum += parseInt(data_rows[i][4]);
        }

        $('#collection-subscribers span').text(subscriberSum);
        $('#collection-ratings #ratings-up em').text(ratingUpSum);
        $('#collection-ratings #ratings-down em').text(ratingDownSum);
        $('#collection-downloads span').text(downloadSum);
    }
};
