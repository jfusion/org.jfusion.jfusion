if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.view = false;
JFusion.enablePagination = false;
JFusion.enableJumpto = false;
JFusion.loadMarkitup = false;

JFusion.highlightDelay = 500;

JFusion.articelUrl = [];

JFusion.onSuccess = function (JSONobject, id) {
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
        JFusion.renderMessages(id, JSONobject.messages);
    }
};

JFusion.OnError = function (messages, id) {
    JFusion.emptyMessage(id);

    var message = {};
    if (messages.indexOf('<!') === 0) {
        message.error = [ Joomla.JText._('SESSION_TIMEOUT') ];
    } else {
        message.error = [ messages ];
    }
    JFusion.renderMessages(id, message);
};

JFusion.renderMessages = function (id, messages) {
    var container = document.id('jfusionMessageArea' + id);

    if (container) {
        var children = $$('#jfusionMessageArea' + id +  ' > *');
        children.destroy();

        Object.each(messages, function (item, type) {
            if (type === 'error') {
                JFusion.changeHash('jfusionMessageArea' + id);
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
            Array.each(item, function (item) {
                var p = new Element('p', {
                    html: item
                });
                p.inject(divList);
            }, this);
            divList.inject(div);
        }, this);
    } else {
        Joomla.renderMessages(messages);
    }
};

JFusion.initializeDiscussbot = function () {
    //load markItUp
    if (JFusion.loadMarkitup) {
        var quickReply = jQuery('.quickReply');

        quickReply.each(function() {
            jQuery(this).markItUp(JFusion.bbcodeSettings);
        });
    }
};

JFusion.updateContent = function (JSONobject) {
    var postArea, buttonArea, postPagination, jfusionDebugContainer;
    if (JSONobject.success) {
        var content = $('jfusioncontent' + JSONobject.data.articleid);

        if (content) {
            //update the post area with the updated content
            postArea = content.getElement('.jfusionPostArea');
            if (postArea) {
                postArea.set('html', JSONobject.data.posts);
            }

            buttonArea = content.getElement('.jfusionButtonArea');
            if (buttonArea) {
                buttonArea.set('html', JSONobject.data.buttons);
            }
            if (JFusion.enablePagination) {
                postPagination = content.getElement('.jfusionPostPagination');
                if (postPagination) {
                    postPagination.set('html', JSONobject.data.pagination);
                }
            }

            if (JSONobject.data.postid) {
                JFusion.highlightPost(JSONobject.data.articleid, JSONobject.data.postid);

                //remove the preview iframe if exists
                if ($('markItUpQuickReply')) {
                    jQuery.markItUp({call: 'previewClose'});
                }
            }
        }
    }
    jfusionDebugContainer = $('jfusionDebugContainer' + JSONobject.data.articleid);
    if (jfusionDebugContainer) {
        jfusionDebugContainer.set('html', JSONobject.data.debug);
    }
};

JFusion.highlightPost = function (articleid, postid) {
    var post, highlight;
    var content = $('jfusioncontent' + articleid);
    if (content) {
        post = content.getElement('#post' + postid);
        if (post) {
            post.setStyle('border', '2px solid #FCFC33');
            highlight = function () {
                post.setStyle('border', '2px solid #afafaf');
            };
            highlight.delay(JFusion.highlightDelay);

            if (JFusion.enableJumpto) {
                JFusion.changeHash('post' + postid);
            }
        }
    }
};

JFusion.refreshPosts = function (id) {
    new Request.JSON({
        url: JFusion.articelUrl[id],
        noCache: true,
        onSuccess: function (JSONobject) {
            JFusion.onSuccess(JSONobject, id);
            JFusion.updateContent(JSONobject);
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject, id);
        }
    }).post({'tmpl': 'component',
            'ajax_request': '1',
            'dbtask': 'update_posts'});
};

JFusion.confirmThreadAction = function (id, task, vars) {
    var container, divBtnContainer, msg;
    var content = $('jfusioncontent' + id);
    if (content) {
        container = content.getElement('.jfusionButtonConfirmationBox');
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
        }
    }
};

JFusion.clearConfirmationBox = function (id) {
    var content = $('jfusioncontent' + id);
    if (content) {
        var container = content.getElement('.jfusionButtonConfirmationBox');
        if (container) {
            container.empty();
        }
    }
};

JFusion.submitAjaxRequest = function (id, task, vars) {
    JFusion.clearConfirmationBox(id);
    var url = JFusion.articelUrl[id];
    new Request.JSON({
        url: url,
        noCache: true,
        onSuccess: function (JSONobject) {
            JFusion.onSuccess(JSONobject, id);
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
    var showdiscussion, jfusionBtnShowreplies;
    var content = $('jfusioncontent' + id);
    if (content) {
        var posts = content.getElement('.jfusionposts');
        if (posts) {
            jfusionBtnShowreplies = content.getElement('.jfusionBtnShowreplies');
            if (jfusionBtnShowreplies) {
                posts.toggle();
                if (posts.isDisplayed()) {
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
            }
        }
    } else {
        if (discusslink !== undefined) {
            window.location = discusslink;
        }
    }
};

JFusion.quote = function (id, pid) {
    var content = $('jfusioncontent' + id);
    if (content) {
        var quickReply = content.getElement('.quickReply');
        var originalText = content.getElement('#originalText' + pid);
        if (quickReply && originalText) {
            quickReply.set('value', quickReply.get('value') + originalText.get('html'));
            quickReply.focus();

            JFusion.changeHash('jfusionQuickReply' + id);
        }
    }
};

JFusion.pagination = function (id) {
    new Request.JSON({
        url: JFusion.articelUrl[id],
        noCache: true,
        onSuccess : function (JSONobject) {
            JFusion.onSuccess(JSONobject, id);
            JFusion.updateContent(JSONobject);
            if (JSONobject.success) {
                JFusion.changeHash('jfusioncontent' + id);
            }
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject, id);
        }
    }).post($('jfusionPaginationForm').toQueryString() + '&tmpl=component&ajax_request=1&dbtask=update_posts');
};

JFusion.submitReply = function (id) {
    var content = $('jfusioncontent' + id);
    if (content) {
        var form = content.getElement('#jfusionQuickReply' + id);
        //show a loading
        JFusion.emptyMessage(id);

        var messages = {};
        messages.message = [ Joomla.JText._('SUBMITTING_QUICK_REPLY') ];
        JFusion.renderMessages(id, messages);

        //update the post area content
        new Request.JSON({
            url: JFusion.articelUrl[id],
            noCache: true,
            onSuccess: function (JSONobject) {
                JFusion.onSuccess(JSONobject, id);
                JFusion.updateContent(JSONobject);
                if (JSONobject.success) {
                    var quickReply = content.getElement('.quickReply');
                    if (quickReply) {
                        quickReply.set('value', '');
                    }
                }
            },
            onError: function (JSONobject) {
                JFusion.OnError(JSONobject, id);
            }
        }).post(form.toQueryString() + '&tmpl=component&ajax_request=1');
    }
    return false;
};

JFusion.emptyMessage = function (id) {
    var messageArea = $('jfusionMessageArea' + id);
    if (messageArea) {
        messageArea.empty();
    }
};

JFusion.changeHash = function (hash) {
    window.location.hash = '';
    window.location.hash = hash;
};