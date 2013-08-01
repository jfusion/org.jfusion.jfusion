if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.applyAll = function () {
    var form = $('adminForm');
    var defaultvalue = form.elements['default_value'].selectedIndex;
    for(var i=0; i<form.elements.length; i++) {
        if (form.elements[i].type === 'select-one') {
            form.elements[i].selectedIndex = defaultvalue;
        }
    }
};