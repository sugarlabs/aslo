var compatibility = {
    viewReport: function() {
        $('#compat-intro').fadeOut('medium', function() {
            $('#compat-home-nav').fadeIn();
            $('#report-intro').fadeIn('medium');
        });
    },
    
    viewReportDetails: function() {
        $('#report-intro').fadeOut('medium', function() {
            $('.compat-box').animate({ width: '950px'}, 'medium', null, function() {
                $('#report-details').fadeIn('medium', function() {
                    $('#report-details .loading').show();
                    $('#report-details-data').load(detailsURL, null, function() {
                        $('#report-details .loading').hide();
                        $('#report-details-data').show();
                    });
                });
            });
        });
        
    },
    
    viewHome: function() {
        $('#report-intro,#report-details,#report-details-data,.loading,#compat-home-nav,#developers-intro,#developers-details,#users-intro').hide();
        $('.compat-box').animate({ width: '650px'}, 'fast');
        $('#compat-intro').fadeIn('medium');
    },
    
    viewDeveloperInfo: function() {
        $('#compat-intro').fadeOut('medium', function() {
            $('#compat-home-nav').fadeIn();
            $('#developers-intro').fadeIn('medium');
        });
    },
    
    viewDeveloperDetails: function() {
        $('#developers-intro').fadeOut('medium', function() {
            $('#developers-details').fadeIn('medium', function() {
                $('#developers-details .loading').show();
                $('#developers-details-data').load(developerStatusURL, null, function() {
                    $('#developers-details .loading').hide();
                    $('#developers-details-data').show();
                });
            });
        });
    },
    
    viewEndUserInfo: function() {
        $('#compat-intro').fadeOut('medium', function() {
            $('#compat-home-nav').fadeIn();
            $('#users-intro').fadeIn('medium');
        });
    }
};