if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.applyAll = function () {
    var defaultvalue = $('default_value').selectedIndex;

    var elements = document.getElements('select[name^=syncError');
    elements.each(function(element) {
        element.selectedIndex = defaultvalue;
    });
};