if(typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.getElement = function(aID) {
    return (document.getElementById) ? document.getElementById(aID) : document.all[aID];
};

JFusion.getIFrameDocument = function(aID) {
    var rv = null;
    var frame = JFusion.getElement(aID);
    // if contentDocument exists, W3C compliant (e.g. Mozilla)

    if (frame.contentDocument) {
        rv = frame.contentDocument;
    } else {
        // bad IE  ;)
        rv = document.frames[aID].document;
    }
    return rv;
};

JFusion.adjustMyFrameHeight = function() {
    var frame = JFusion.getElement("jfusioniframe");
    frame.height = JFusion.getIFrameDocument("jfusioniframe").body.offsetHeight;

    window.scrollTo(window.pageYOffset, JFusion.getOffsetTop(frame));
};

JFusion.getOffsetTop = function(element) {
    var el, top;
    el = element;
    top = 0;
    while( el && !isNaN( el.offsetTop ) ) {
        top += el.offsetTop;
        el = el.offsetParent;
    }
    return top;
};