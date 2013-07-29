if('undefined'===typeof JFusion) {
    var JFusion = {};
    JFusion.Text = [];
    JFusion.jumptoDiscussion = true;
    JFusion.messageSlide = false;
    JFusion.delayHiding = false;
    JFusion.confirmationBoxSlides = [];

    JFusion.updatePostArea = false;

    JFusion.view = false;
    JFusion.enablePagination = false;
    JFusion.enableAjax = false;
    JFusion.enableJumpto = false;
    JFusion.loadMarkitup = false;
}

JFusion.JText = function(key) {
    var text;
    var key = key.toUpperCase();
    if (this.Text[key]) {
        text = this.Text[key];
    } else {
        text = key;
    }
    return text;
};

JFusion.OnError = function(messages, force) {
    JFusion.emptyMessage();
    if (messages.indexOf('<!') == 0) {
        messages = [ this.JText('SESSION_TIMEOUT') ];
    } else {
        messages = [ messages ];
    }
    this.OnMessage('error', messages, force);
};

JFusion.OnMessages = function(messages, force) {
    JFusion.emptyMessage();

    this.OnMessage('message', messages.message, force);
    this.OnMessage('notice', messages.notice, force);
    this.OnMessage('warning', messages.warning, force);
    this.OnMessage('error', messages.error, force);

    JFusion.delayHiding = setTimeout(function () {
        if (JFusion.messageSlide) {
            JFusion.messageSlide.slideOut();
        }
    }, 15000);
};

JFusion.OnMessage = function(type, messages, force) {
    if (messages instanceof Array) {
        if (messages.length) {
            if (type == 'error') {
                window.location = '#jfusionMessageArea';
            }

            //stop a slideOut if pending
            if (JFusion.delayHiding) {
                clearTimeout(JFusion.delayHiding);
            }

            var errorlist = { 'error' : 'alert-error', 'warning' : '', 'notice' : 'alert-info', 'message' : 'alert-success'};

            var div = new Element('div', {'class' : 'alert'+' '+ errorlist[type] });

            new Element('h4',{'class': 'alert-heading', 'html' : this.JText(type) }).inject(div);
            Array.each(messages, function(message, index) {
                new Element('p' , { 'html' : message } ).inject(div);
                if (force) {
                    alert(message);
                }
            });
            var messageArea = $('jfusionMessageArea');
            if (messageArea) {
                div.inject(messageArea);
            }

            if (JFusion.messageSlide) {
                JFusion.messageSlide.slideIn();
            }
        }
    }
};

JFusion.initializeDiscussbot = function() {
    if (JFusion.jumptoDiscussion) {
        window.location = '#discussion';
    }

    //only initiate if the div container exists and if the var has not been declared Fx.Slide
    if ($('jfusionMessageArea')) {
        JFusion.messageSlide = new Fx.Slide('jfusionMessageArea');
        JFusion.messageSlide.hide();
    }

    JFusion.initializeConfirmationBoxes();

    // this code will send a data object via a GET request and alert the retrieved data.

    JFusion.updatePostArea = new Request.JSON({
        noCache: true,
        onSuccess: function(JSONobject) {
            JFusion.updateContent(JSONobject);
            $('quickReply').set('value','');
        }, onError: function(JSONobject) {
            JFusion.OnError(JSONobject);
        }
    });

    //load markItUp
    if (JFusion.loadMarkitup) {
        var quickReply = jQuery('#quickReply');
        if (quickReply) {
            quickReply.markItUp(mySettings);
        }
    }
};

JFusion.updateContent = function(JSONobject) {
    if (JSONobject.status) {
        //update the post area with the updated content
        var postArea = $('jfusionPostArea');
        if (postArea) {
            postArea.set('html',JSONobject.posts);
        }

        var buttonArea = $('jfusionButtonArea' + JSONobject.articleid);
        if (buttonArea) {
            buttonArea.set('html',JSONobject.buttons);
        }
        if (JFusion.enablePagination) {
            var postPagination = $('jfusionPostPagination');
            if (postPagination) {
                postPagination.set('html',JSONobject.pagination);
            }
        }

        if (JSONobject.postid) {
            JFusion.highlightPost('post' + JSONobject.postid);

            //remove the preview iframe if exists
            if ($('markItUpQuickReply')) {
                jQuery.markItUp({ call: 'previewClose' });
            }
        }
    }
    JFusion.OnMessages(JSONobject.messages);
    var jfusionDebugContainer = $('jfusionDebugContainer' + JSONobject.articleid);
    if (jfusionDebugContainer) {
        jfusionDebugContainer.set('html', JSONobject.debug);
    }
};

JFusion.initializeConfirmationBoxes = function () {
    var containers = $$('div.jfusionButtonConfirmationBox');
    if (containers) {
        for (var i = 0; i < containers.length; i++) {
            var divId = containers[i].get('id');
            if (typeof (JFusion.confirmationBoxSlides[divId]) != 'object') {
                JFusion.confirmationBoxSlides[divId] = new Fx.Slide(divId);
                JFusion.confirmationBoxSlides[divId].hide();
            }
        }
    }
};

JFusion.highlightPost = function(postid) {
    var post = $(postid);
    if (post) {
        post.setStyle('border', '2px solid #FCFC33');
        (function () { post.setStyle('border', '2px solid #afafaf'); }).delay(5000);

        if (JFusion.enableJumpto) {
            window.location = '#' + postid;
        }
    }
};

JFusion.refreshPosts = function(id) {
    JFusion.updatePostArea.post('tmpl=component&ajax_request=1&dbtask=update_posts');
};

JFusion.confirmThreadAction = function(id, task, vars, url) {
    var container = $('jfusionButtonConfirmationBox' + id);
    if (container) {
        //clear anything already there
        container.empty();
        var msg = '';
        if (task == 'create_thread') {
            msg = JFusion.JText('CONFIRM_THREAD_CREATION');
        } else if (task == 'unpublish_discussion') {
            msg = JFusion.JText('CONFIRM_UNPUBLISH_DISCUSSION');
        } else if (task == 'publish_discussion') {
            msg = JFusion.JText('CONFIRM_PUBLISH_DISCUSSION');
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
        var divBtnContainer = new Element('div', {
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
            value: JFusion.JText('BUTTON_CANCEL'),
            events: {
                click: function () {
                    JFusion.clearConfirmationBox(id);
                }
            }
        }).inject(divBtnContainer);

        //create the buttons
        if (task == 'create_thread') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: JFusion.JText('BUTTON_INITIATE'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars, url);
                    }
                }
            }).inject(divBtnContainer);
        } else if (task == 'publish_discussion') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: JFusion.JText('BUTTON_REPUBLISH_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars, url);
                    }
                }
            }).inject(divBtnContainer);

            new Element('input', {
                type: 'button',
                'class': 'button',
                value: JFusion.JText('BUTTON_PUBLISH_NEW_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, 'create_thread', vars, url);
                    }
                }
            }).inject(divBtnContainer);
        } else if (task == 'unpublish_discussion') {
            new Element('input', {
                type: 'button',
                'class': 'button',
                value: JFusion.JText('BUTTON_UNPUBLISH_DISCUSSION'),
                styles: {
                    marginLeft: '3px'
                },
                events: {
                    click: function () {
                        JFusion.submitAjaxRequest(id, task, vars, url);
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

JFusion.clearConfirmationBox = function(id) {
    var container = $('jfusionButtonConfirmationBox' + id);
    if (container) {
        container.empty();
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + id].hide();
    }
};

JFusion.submitAjaxRequest = function (id, task, vars, url) {
    JFusion.clearConfirmationBox(id);

    new Request.JSON({
        url: url ,
        noCache: true,
        onSuccess: function(JSONobject) {
            window.location = url;
        }
    }).post('tmpl=component&ajax_request=1&dbtask=' + task + '&articleId=' + id + vars);
};

JFusion.toggleDiscussionVisibility = function(id, override, discusslink) {
    var showdiscussion = '';
    var discussion = $('discussion');
    if (discussion) {
        var jfusionBtnShowreplies = $('jfusionBtnShowreplies' + id);
        var state = discussion.style.display;
        if (state == 'none') {
            discussion.style.display = 'block';
            jfusionBtnShowreplies.set('html',JFusion.JText('HIDE_REPLIES'));
            showdiscussion = 1;
        } else {
            discussion.style.display = 'none';
            jfusionBtnShowreplies.set('html',JFusion.JText('SHOW_REPLIES'));
            showdiscussion = 0;
        }
        if (override !== undefined) {
            showdiscussion = override;
        }
        new Request.JSON({
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

JFusion.quote = function(pid) {
    var quickReply = $('quickReply');
    quickReply.set('value', quickReply.get('value')+ $('originalText' + pid).get('html'));
    quickReply.focus();

    window.location = '#jfusionQuickReply';
};

JFusion.pagination = function() {
    new Request.JSON({
        noCache: true,
        onSuccess : function (JSONobject) {
            JFusion.updateContent(JSONobject);
            window.location = '#discussion';
        }, onError: function(JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).get($('jfusionPaginationForm').toQueryString()+'&tmpl=component&ajax_request=1&dbtask=update_posts');
};

JFusion.submitReply = function(id) {
    if (JFusion.enableAjax) {
        var form = $('jfusionQuickReply' + id);
        //show a loading
        JFusion.emptyMessage();

        JFusion.OnMessage('message', [JFusion.JText('SUBMITTING_QUICK_REPLY')]);

        //update the post area content
        JFusion.updatePostArea.post(form.toQueryString()+'&tmpl=component&ajax_request=1');
        return false;
    }
    return true;
};

JFusion.emptyMessage = function() {
    if (JFusion.messageSlide) {
        var messageArea = $('jfusionMessageArea');
        messageArea.empty();
        JFusion.messageSlide.hide();
    }
};