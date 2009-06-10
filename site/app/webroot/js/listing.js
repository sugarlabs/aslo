//toggle visibility
function toggleNode(node) {
    var nodes = document.getElementsByTagName('ul');
    for (var i = 0; i < nodes.length; i++) {
        if (nodes[i].className == node) {
            if (nodes[i].style.display == 'none') {
                nodes[i].style.display = '';
            }
            else {
                nodes[i].style.display = 'none';
            }
        }
    }
}

function expandNodes() {
    var nodes = document.getElementsByTagName('ul');
    for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.display = '';
    }
}

function collapseNodes() {
    var nodes = document.getElementsByTagName('ul');
    for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.display = 'none';
    }
}
