if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.confirmSubmitLanguage = function (action) {
    var confirmBox = new Element('div');
    confirmBox.appendChild(new Element('div', {
        'html': JFusion.JText('INSTALL_UPGRADE_LANGUAGE_PACKAGE')
    }));

    confirmBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': JFusion.JText('INSTALL'),
        'style': 'float: right;',
        'events': {
            'click': function () {
                var install = $('install');
                install.install_url.value = action;
                install.submit();
                SqueezeBox.close();
            }
        }
    }));
    SqueezeBox.open(confirmBox, {
        handler : 'adopt',
        overlayOpacity : 0.7,
        size: {x: 320,
            y: 120}
    });
};