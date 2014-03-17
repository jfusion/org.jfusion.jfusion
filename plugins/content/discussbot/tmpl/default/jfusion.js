if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.jumptoDiscussion = true;
JFusion.messageSlide = false;
JFusion.delayHiding = false;
JFusion.confirmationBoxSlides = [];

JFusion.view = false;
JFusion.enablePagination = false;
JFusion.enableJumpto = false;
JFusion.loadMarkitup = false;

JFusion.timeout = 15000;
JFusion.highlightDelay = 500;

JFusion.articelUrl = [];

JFusion.onSuccess = function (JSONobject) {
    if (!JSONobject.success && JSONobject.message) {
        if (!JSONobject.messages) {
            JSONobject.messages = {};
        }
        if (!JSONobject.messages.error) {
            JSONobject.messages.error = [];
        }
        JSONobject.messages.error[JSONobject.messages.error.length] = JSONobject.message;
    }
    if (JSONobject.messages) {
        JFusion.renderMessages(JSONobject.messages);
    }
};

JFusion.OnError = function (messages) {
    JFusion.emptyMessage();

    var message = {};
    if (messages.indexOf('<!') === 0) {
        message.error = [ Joomla.JText._('SESSION_TIMEOUT') ];
    } else {
        message.error = [ messages ];
    }
    JFusion.renderMessages(message);
};

JFusion.renderMessages = function (messages) {
    var container = document.id('jfusionMessageArea');

    if (container) {
        var children = $$('#jfusionMessageArea > *');
        children.destroy();

        Object.each(messages, function (item, type) {
            if (type === 'error') {
                window.location = '#jfusionMessageArea';
            }

            //stop a slideOut if pending
            if (JFusion.delayHiding) {
                clearTimeout(JFusion.delayHiding);
            }

            var div = new Element('div', {
                id: 'system-message',
                'class': 'alert alert-' + type
            });
            div.inject(container);
            var h4 = new Element('h4', {
                'class' : 'alert-heading',
                html: Joomla.JText._(type)
            });
            h4.inject(div);
            var divList = new Element('div');
            Array.each(item, function (item, index, object) {
                if (JFusion.messageSlide) {
                    JFusion.messageSlide.slideIn();
                }
                var p = new Element('p', {
                    html: item
                });
                p.inject(divList);
            }, this);
            divList.inject(div);
        }, this);

        JFusion.delayHiding = setTimeout(function () {
            if (JFusion.messageSlide) {
                JFusion.messageSlide.slideOut();
            }
        }, JFusion.timeout);
    } else {
        Joomla.renderMessages(messages);
    }
};

JFusion.initializeDiscussbot = function () {
    if (JFusion.jumptoDiscussion) {
        window.location = '#discussion';
    }

    //only initiate if the div container exists and if the var has not been declared Fx.Slide
    if ($('jfusionMessageArea')) {
        JFusion.messageSlide = new Fx.Slide('jfusionMessageArea');
        JFusion.messageSlide.hide();
    }

    JFusion.initializeConfirmationBoxes();

    //load markItUp
    if (JFusion.loadMarkitup) {
        var quickReply = jQuery('#quickReply');
        if (quickReply) {
            quickReply.markItUp(JFusion.bbcodeSettings);
        }
    }
};

JFusion.updateContent = function (JSONobject) {
    var postArea, buttonArea, postPagination, jfusionDebugContainer;
    if (JSONobject.success) {
        //update the post area with the updated content
        postArea = $('jfusionPostArea');
        if (postArea) {
            postArea.set('html', JSONobject.data.posts);
        }

        buttonArea = $('jfusionButtonArea' + JSONobject.data.articleid);
        if (buttonArea) {
            buttonArea.set('html', JSONobject.data.buttons);
        }
        if (JFusion.enablePagination) {
            postPagination = $('jfusionPostPagination');
            if (postPagination) {
                postPagination.set('html', JSONobject.data.pagination);
            }
        }

        if (JSONobject.data.postid) {
            JFusion.highlightPost('post' + JSONobject.data.postid);

            //remove the preview iframe if exists
            if ($('markItUpQuickReply')) {
                jQuery.markItUp({call: 'previewClose'});
            }
        }
    }
    jfusionDebugContainer = $('jfusionDebugContainer' + JSONobject.data.articleid);
    if (jfusionDebugContainer) {
        jfusionDebugContainer.set('html', JSONobject.data.debug);
    }
};

JFusion.initializeConfirmationBoxes = function () {
    var containers, i, divId;
    containers = $$('div.jfusionButtonConfirmationBox');
    if (containers) {
        for (i = 0; i < containers.length; i++) {
            divId = containers[i].get('id');
            if (typeof (JFusion.confirmationBoxSlides[divId]) !== 'object') {
                JFusion.confirmationBoxSlides[divId] = new Fx.Slide(divId);
                JFusion.confirmationBoxSlides[divId].hide();
            }
        }
    }
};

JFusion.highlightPost = function (postid) {
    var post, highlight;
    post = $(postid);
    if (post) {
        post.setStyle('border', '2px solid #FCFC33');
        highlight = function () {
            post.setStyle('border', '2px solid #afafaf');
        };
        highlight.delay(JFusion.highlightDelay);

        if (JFusion.enableJumpto) {
            window.location = '#' + postid;
        }
    }
};

JFusion.refreshPosts = function (id) {
    new Request.JSON({
        url: JFusion.articelUrl[id],
        noCache: true,
        onSuccess: function (JSONobject) {
            JFusion.onSuccess(JSONobject);
            JFusion.updateContent(JSONobject);
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).post({'tmpl': 'component',
            'ajax_request': '1',
            'dbtask': 'update_posts'});
};

JFusion.confirmThreadAction = function (id, task, vars) {
    var container, divBtnContainer, msg;
    container = $('jfusionButtonConfirmationBox' + id);
    if (container) {
        //clear anything already there
        container.empty();
        msg = '';
        if (task === 'create_thread') {
            msg = Joomla.JText._('CONFIRM_THREAD_CREATION');
        } else if (task === 'unpublish_discussion') {
            msg = Joomla.JText._('CONFIRM_UNPUBLISH_DISCUSSION');
        } else if (task === 'publish_discussion') {
            msg = Joomla.JText._('CONFIRM_PUBLISH_DISCUSSION');
        }

        //set the confirmation text
        new Element('span', {
            text: msg,
            styles: {
                fontWeight: "bold",
                display: "block",
                fontSize: "13px",
                padding: "5px"
            }
        }).inject(container);

        //create a div for the buttons
        divBtnContainer = new Element('div', {
            styles: {
                display: 'block',
                textAlign: 'right',
                marginTop: '5px',
                marginBottom: '5px'
            }
        });

        //create standard cancel button
        new Element('input', {
            type: 'button',
            'class': 'button',
            value: Joomla.JText._('BUTTON_CANCEL'),
            events: {
                click: function () {
                    JFusion.clearConfirmationBox(id);
                }
            }
        }).inject(divBtnContainer);

        //create the buttons
        if (task === 'create_thread') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: Joomla.JText._('BUTTON_INITIATE'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars);
                    }
                }
            }).inject(divBtnContainer);
        } else if (task === 'publish_discussion') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: Joomla.JText._('BUTTON_REPUBLISH_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars);
                    }
                }
            }).inject(divBtnContainer);

            new Element('input', {
                type: 'button',
                'class': 'button',
                value: Joomla.JText._('BUTTON_PUBLISH_NEW_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, 'create_thread', vars);
                    }
                }
            }).inject(divBtnContainer);
        } else if (task === 'unpublish_discussion') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: Joomla.JText._('BUTTON_UNPUBLISH_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars);
                    }
                }
            }).inject(divBtnContainer);
        }

        //attach the buttons
        divBtnContainer.inject(container);

        //show the message
        container.show();
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + id].slideIn();
    }
};

JFusion.clearConfirmationBox = function (id) {
    var container = $('jfusionButtonConfirmationBox' + id);
    if (container) {
        container.empty();
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + id].hide();
    }
};

JFusion.submitAjaxRequest = function (id, task, vars) {
    JFusion.clearConfirmationBox(id);
    var url = JFusion.articelUrl[id];
    new Request.JSON({
        url: url,
        noCache: true,
        onSuccess: function (JSONobject) {
            JFusion.onSuccess(JSONobject);
            if (JSONobject.success) {
                window.location = url;
            }
        },
        onError: function (JSONobject) {
            JFusion.onError(JSONobject);
            window.location = url;
        }
    }).post('tmpl=component&ajax_request=1&dbtask=' + task + '&articleId=' + id + vars);
};

JFusion.toggleDiscussionVisibility = function (id, discusslink) {
    var showdiscussion, discussion, jfusionBtnShowreplies;
    discussion = $('discussion');
    if (discussion) {
        jfusionBtnShowreplies = $('jfusionBtnShowreplies' + id);
        discussion.toggle();
        if (discussion.isDisplayed()) {
            jfusionBtnShowreplies.set('html', Joomla.JText._('HIDE_REPLIES'));
            showdiscussion = 1;
        } else {
            jfusionBtnShowreplies.set('html', Joomla.JText._('SHOW_REPLIES'));
            showdiscussion = 0;
        }
        if (discusslink !== undefined) {
            showdiscussion = 1;
        }
        new Request.JSON({
            url: JFusion.articelUrl[id],
            noCache: true,
            onComplete: function () {
                if (discusslink !== undefined) {
                    window.location = discusslink;
                }
            }
        }).post({'tmpl': 'component',
                'ajax_request': 1,
                'show_discussion': showdiscussion});
    } else {
        if (discusslink !== undefined) {
            window.location = discusslink;
        }
    }
};

JFusion.quote = function (pid) {
    var quickReply = $('quickReply');
    quickReply.set('value', quickReply.get('value') + $('originalText' + pid).get('html'));
    quickReply.focus();

    window.location = '#jfusionQuickReply';
};

JFusion.pagination = function (id) {
    new Request.JSON({
        url: JFusion.articelUrl[id],
        noCache: true,
        onSuccess : function (JSONobject) {
            JFusion.onSuccess(JSONobject);
            JFusion.updateContent(JSONobject);
            if (JSONobject.success) {
                window.location = '#discussion';
            }
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).post($('jfusionPaginationForm').toQueryString() + '&tmpl=component&ajax_request=1&dbtask=update_posts');
};

JFusion.submitReply = function (id) {
    var form = $('jfusionQuickReply' + id);
    //show a loading
    JFusion.emptyMessage();

    var messages = {};
    messages.message = [ Joomla.JText._('SUBMITTING_QUICK_REPLY') ];
    JFusion.renderMessages(messages);

    //update the post area content
    new Request.JSON({
        url: JFusion.articelUrl[id],
        noCache: true,
        onSuccess: function (JSONobject) {
            JFusion.onSuccess(JSONobject);
            JFusion.updateContent(JSONobject);
            if (JSONobject.success) {
                $('quickReply').set('value', '');
            }
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).post(form.toQueryString() + '&tmpl=component&ajax_request=1');
    return false;
};

JFusion.emptyMessage = function () {
    if (JFusion.messageSlide) {
        var messageArea = $('jfusionMessageArea');
        messageArea.empty();
        JFusion.messageSlide.hide();
    }
};