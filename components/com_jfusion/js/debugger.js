if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.toggleDebugger = function (event) {
    var evtSource;
    evtSource = window.event ? window.event.srcElement : event.target;
    while (evtSource.nextSibling === null) {
        evtSource = evtSource.parentNode;
    }
    var tNode = evtSource.nextSibling;
    while (tNode.nodeType !== 1) {
        tNode = tNode.nextSibling;
    }
    tNode.style.display = (tNode.style.display !== 'none') ? 'none' : 'block';
};