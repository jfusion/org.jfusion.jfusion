if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.checked = false;

JFusion.applyAll = function () {
    var form = $('adminForm');
    JFusion.checked = (JFusion.checked === false);
    for(var i=0; i<form.elements.length; i++) {
        if (form.elements[i].type === 'checkbox') {
            form.elements[i].checked = JFusion.checked;
        }
    }
};