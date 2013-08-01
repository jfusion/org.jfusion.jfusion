if (typeof JFusion === 'undefined') {
    var JFusion = {};
}
JFusion.doShowHide = function (item) {
    var obj=$('x'+item);
    var col=$(item);
    if (obj.isDisplayed()) {
        col.set('html', '[-]');
    } else {
        col.set('html', '[+]');
    }
    obj.toggle();
};