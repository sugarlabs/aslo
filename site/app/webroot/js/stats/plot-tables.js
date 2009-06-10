/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   l.m.orchard <lorchard@mozilla.com> (Original Author)
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

/**
 * Machinery to dynamically build tables based on incoming plot data.
 */
var PlotsTables = (function() {

    var MAX_ROWS = 28;

    var source_names = [
        'downloads', 'updatepings', 'count', 'events-firefox', 'events-addon'
    ];
    var event_names = [
        'onAddMany', 'onAddOne', 'onClear'
    ];

    return {

        init: function() {
            return this;
        },

        summary_downloads_onAddMany: function(source) {
            // Clear any existing tables when the summary comes up.
            $('#stats-table').hide();
            $('#stats-table-instance').remove();
        },

        week_over_week_count_onAddMany: function(source) {
            // Clear any existing tables when week-over-week comes up
            $('#stats-table').hide();
            $('#stats-table-instance').remove();
        },

        defined_count_onAddMany: function(source) {

            // Set the URL for the download link.
            $('#stats-table>a').attr('href', Plots.currentCSV);
                                     
            // Convert all the events into an array of arrays.
            var data_rows = [],
                evt, i_evt = source.getAllEventIterator();
            while (evt = i_evt.next()) {
                data_rows.push(
                    [ evt.getTime() ].concat( evt.getValues() )
                );
            }

            // Sort the rows in reverse-chronological order and take 
            // the latest MAX_ROWS off the top.
            data_rows.sort(function(b,a) {
                var a=a[0], b=b[0];
                return (a==b) ? 0 : ( (a<b) ? -1 : 1 );
            });
            data_rows = data_rows.slice(0, MAX_ROWS);

            // Instantiate a clone from the hidden stats table template.
            var stats_table = $('#stats-table>.template')
                .clone().removeClass('template')
                .attr('id', 'stats-table-instance');

            // Fill in the header with the available fields.
            var header = stats_table.find('tr.header'),
                h_col = header.find('th').remove();
            for (var i=0,field; field=Plots.availableFields[i]; i++) {
                if (field.substr(0,1) == '{') {
                    // Try to pretty up headers that look like app GUIDs
                    field = plotSelection
                        .getApplicationName(field)['itemName'];
                }
                h_col.clone().html(field).appendTo(header);
            }

            // Mark the first/last headers for CSS.
            header
                .find('th:first').addClass('first').end()
                .find('th:last').addClass('last');

            // Remove any existing table and insert the new one.
            $('#stats-table-instance').remove();
            $('#stats-table').append(stats_table).show();

            // Get localized date format and un-escape it.
            var date_fmt = localized.date.replace(/&#37;/g,'%');

            // Wrap the table population in a self-calling timeout function 
            // in order to keep the browser from completely freezing during 
            // the process.  Also provides cheap loading animation as rows 
            // flow in.
            var tmpl_row = stats_table.find('tr.row').remove();
            var is_even_row = false;
            (function() {

                // Try getting a data row, bail out if none left.
                var values = data_rows.shift();
                if (!values)  {
                    // Tag the first and last rows.
                    stats_table
                        .find('tr:first').addClass('first').end()
                        .find('tr:last').addClass('last').end();
                    return;
                }

                // Format the date using the localized format
                values[0] = values[0].strftime(date_fmt);

                // Clone a new row and populate it with the values.
                var row = tmpl_row.clone(),
                    col = row.find('td.col').remove();
                for (var j=0,value; value=values[j]; j++) {
                    col.clone().text(value).appendTo(row);
                }

                // Set the even/odd row class and mark first/last columns.
                row.addClass( is_even_row ? 'even' : 'odd' )
                    .find('td:first').addClass('first').end()
                    .find('td:last').addClass('last');
                is_even_row = !is_even_row;

                // Finally, add the row to the table and schedule a call for
                // the next row.
                stats_table.append(row);
                setTimeout(arguments.callee, 0);

            })();

        },

        addListeners: function(kind, dataSources) {
            for (var i=0,name; name=source_names[i]; i++) {
                var source = dataSources[name];
                if (source) this.addListener(kind, name, source);
            }
        },

        addListener: function(kind, source_name, source) {
            var listener = {},
                fn_pre = kind + '_' + source_name;
            for (var i=0,evt_name; evt_name=event_names[i]; i++) {
                var fn = fn_pre + '_' + evt_name;
                if (this[fn]) 
                    listener[evt_name] = this.makeListener(fn, source);
            }
            source.addListener(listener);
        },

        makeListener: function(fn, source) {
            var that = this;
            return function() { 
                return that[fn](source) 
            }
        },

        EOF: null
    }
})().init();
