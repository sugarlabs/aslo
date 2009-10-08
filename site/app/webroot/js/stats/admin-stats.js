var Plots = {
    timeplot_id: '',
    timeplotCount: 0,
    timeplot: null,
    currentCSV: '',
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
        roundValues: false,
        valueFormatter: numberFormat,
        dotRadius: 2.0,
        lineWidth: 2.0
    },
    plotInfoConfigs: {
        'contributions': {
            id: 'plot-contributions',
            dotColor: '#009933',
            lineColor: '#009933',
            fillColor: '#009933',
            fillGradient: true,
            column: 1,
            valueFormatter: dollarFormat
        }
    },

    initialize: function() {
        $('#contributions-group-by').change(this.contributionsGroupByChange);
        this.plot();
    },

    newTimeplot: function() {
        if (this.timeplot_id != '') {
            $('#' + this.timeplot_id).remove();
            this.timeplot = null;
        }
        this.timeplotCount++;
        
        $('#not-enough-data').hide();

        if (this.dataTable) {
            $('#stats-table-listing').hide();
            this.dataTable.clearTable();
        }
        
        this.timeplot_id = 'timeplot' + this.timeplotCount;
        $('#timeplot-container').append('<div id="' + this.timeplot_id + '" class="timeplot"></div>');
    },

    plot: function() {
        // this is the only plot we have so far
        var metric = 'contributions';
        this.currentCSV = adminURL + 'csv/' + metric;

        // clear the way for a new timeplot
        this.newTimeplot();

        // start with a fresh eventsource 
        var eventSource = new Timeplot.DefaultEventSource();

        // instantiate plotinfo
        var plotConfig = {};
        $.extend(plotConfig, this.plotInfoCommonConfig, this.plotInfoConfigs[metric], {
            timeGeometry: new Timeplot.DefaultTimeGeometry(this.timeGeometryConfig),
            valueGeometry: new Timeplot.DefaultValueGeometry(this.valueGeometryConfig),
            dataSource: new Timeplot.ColumnSource(eventSource, this.plotInfoConfigs[metric].column)
        });
        var plotInfo = Timeplot.createPlotInfo(plotConfig);

        // hook up datatable
        if (this.dataTable) {
            this.dataTable.listenTo(eventSource);
            this.dataTable.setDownloadLink(this.currentCSV);
            this.dataTable.config['valueFormatters'] = [null, dollarFormat, null, dollarFormat];
            $('#stats-table-listing').show();
            this.dataTable.show();
            this.dataTable.showLoading();

            // reset group-by dropdown
            $('#contributions-group-by option:selected').removeAttr('selected');
            $("#contributions-group-by option[value='date']").attr('selected', 'selected');
        }

        this.timeplot = Timeplot.create(document.getElementById(this.timeplot_id), [plotInfo]);
        this.timeplot.loadText(this.currentCSV, ',', eventSource, Plots.parseFields);
    },

    parseFields: function(text) {
        if (Plots.dataTable) {
            Plots.dataTable.setHeaders(parseRawFields(text));
        }
        return text;
    },

    contributionsGroupByChange: function() {
        // this = select node
        var groupBy = $('option:selected', this).val();
        var url = adminURL + 'csv/contributions?group_by='+groupBy;
        var eventSource = new Timeplot.DefaultEventSource();

        Plots.dataTable.clearTable();
        Plots.dataTable.showLoading();
        Plots.dataTable.listenTo(eventSource);
        Plots.dataTable.setDownloadLink(url);

        if (groupBy == 'addon') {
            Plots.dataTable.config['valueFormatters'] = [null, addonLinkFormat, dollarFormat, null, dollarFormat];
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
