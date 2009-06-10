var colors = {
    counter: 0,
    colors: [
        '#3366CC', // blue
        '#9966CC', // purple
        '#66CCCC', // green
        '#FF9933', // orange
        '#CC3300', // red
        '#999933', // yellow
        '#666666', // gray
        '#3399CC', // blue
        '#CC99CC', // purple
        '#66CC99', // green
        '#CC6666', // red
        '#009933', // green
        '#663333' // red
    ],
    
    getNext: function() {
        if (this.counter > colors.length)
            this.resetCounter();
        
        var color = this.colors[this.counter];
        
        this.counter++;
        
        return color;
    },
    
    resetCounter: function() {
        this.counter = 0;
    }
    
};