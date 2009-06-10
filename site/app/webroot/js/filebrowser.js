/************************************************
*              files/browse                     *
************************************************/
//Expand/collapse the file listing
function toggleFileListing() {
    var div = document.getElementById('fileListing');
    if (div.style.display == 'none') {
        Effect.SlideDown(div);
    }
    else {
        Effect.SlideUp(div);
    }
}

//Change iframe location to selected location
function viewFile(file) {
    document.getElementById('fileBrowser').src = file;
}
