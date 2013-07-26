if('undefined'===typeof JFusion) {
    var JFusion = {};
    JFusion.Text = [];
    JFusion.jumptoDiscussion = true;
    JFusion.messageArea = false;
    JFusion.ajaxMessageSlide = false;
    JFusion.buttonArea = false;
    JFusion.delayHiding = false;
    JFusion.confirmationBoxSlides = [];

    JFusion.updatePostArea = false;
    JFusion.threadid = 0;

    JFusion.view = false;
    JFusion.enablePagination = false;
    JFusion.enableAjax = false;
    JFusion.enableJumpto = false;

    JFusion.articleId = 0;
    JFusion.articleUrl = '';
    JFusion.debug = false;
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
    var jfusionMessageArea = $('jfusionMessageArea');
    jfusionMessageArea.empty();
    if (messages.indexOf('<!') == 0) {
        messages = [ this.JText('SESSION_TIMEOUT') ];
    } else {
        messages = [ messages ];
    }
    this.OnMessage('error', messages, force);
};

JFusion.OnMessages = function(messages, force) {
    var jfusionMessageArea = $('jfusionMessageArea');
    jfusionMessageArea.empty();

    this.OnMessage('message', messages.message, force);
    this.OnMessage('notice', messages.notice, force);
    this.OnMessage('warning', messages.warning, force);
    this.OnMessage('error', messages.error, force);

    JFusion.delayHiding = setTimeout(function () {
        JFusion.ajaxMessageSlide.slideOut();
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

            var jfusionMessageArea = $('jfusionMessageArea');

            var errorlist = { 'error' : 'alert-error', 'warning' : '', 'notice' : 'alert-info', 'message' : 'alert-success'};

            var div = new Element('div', {'class' : 'alert'+' '+ errorlist[type] });

            new Element('h4',{'class': 'alert-heading', 'html' : this.JText(type) }).inject(div);
            Array.each(messages, function(message, index) {
                new Element('p' , { 'html' : message } ).inject(div);
                if (force) {
                    alert(message);
                }
            });
            div.inject(jfusionMessageArea);

            JFusion.ajaxMessageSlide.slideIn();
        }
    }
};

JFusion.initializeDiscussbot = function() {
    if (JFusion.jumptoDiscussion) {
        window.location = '#discussion';
    }

    //only initiate if the div container exists and if the var has not been declared Fx.Slide
    JFusion.messageArea = $('jfusionMessageArea');
    if (typeof (JFusion.ajaxMessageSlide) != 'object' && JFusion.messageArea) {
        JFusion.ajaxMessageSlide = new Fx.Slide('jfusionMessageArea');
        JFusion.ajaxMessageSlide.hide();
    }

    if ($('jfusionButtonConfirmationBox' + JFusion.articleId) && typeof (JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + JFusion.articleId]) != 'object') {
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + JFusion.articleId] = new Fx.Slide('jfusionButtonConfirmationBox' + JFusion.articleId);
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + JFusion.articleId].hide();
    }

    JFusion.buttonArea = $('jfusionButtonArea' + JFusion.articleId);

    // this code will send a data object via a GET request and alert the retrieved data.

    JFusion.updatePostArea = new Request.JSON({url: JFusion.articleUrl ,
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

    //get ajax ready for submission
    if (JFusion.enableAjax && JFusion.messageArea) {
        JFusion.prepareAjax();
    }
};

JFusion.updateContent = function(JSONobject) {
    if (JSONobject.status) {
        //update the post area with the updated content
        var postArea = $('jfusionPostArea');
        if (postArea) {
            postArea.set('html',JSONobject.posts);
        }
        if (JFusion.buttonArea) {
            JFusion.buttonArea.set('html',JSONobject.buttons);
        }
        if (JFusion.enablePagination) {
            var postPagination = $('jfusionPostPagination');
            if (postPagination) {
                postPagination.set('html',JSONobject.pagination);
            }
        }

        var submittedPostId = $('submittedPostId');
        if (submittedPostId) {
            JFusion.highlightPost('post' + submittedPostId.get('html'));

            //remove the preview iframe if exists
            if ($('markItUpQuickReply')) {
                jQuery.markItUp({ call: 'previewClose' });
            }
        }
    }
    JFusion.OnMessages(JSONobject.messages);
    var jfusionDebugContainer = $('jfusionDebugContainer' + JFusion.articleId);
    if (jfusionDebugContainer) {
        jfusionDebugContainer.set('html', JSONobject.debug);
    }
};

JFusion.initializeConfirmationBoxes = function () {
    var i;
    var containers = $$('div.jfusionButtonConfirmationBox');
    if (containers) {
        for (i = 0; i < containers.length; i++) {
            var divId = containers[i].get('id');
            if (typeof (JFusion.confirmationBoxSlides[divId]) != 'object') {
                JFusion.confirmationBoxSlides[divId] = new Fx.Slide(divId);
                JFusion.confirmationBoxSlides[divId].hide();
            }
        }
    }
};

JFusion.prepareAjax = function() {
    var i;
    var submitpost = $('submitpost');

    if (submitpost) {
        //add the submitpost function
        submitpost.addEvent('click', function (e) {
            //show a loading
            var jfusionMessageArea = $('jfusionMessageArea');
            jfusionMessageArea.empty();
            JFusion.ajaxMessageSlide.hide();

            JFusion.OnMessage('message', [JFusion.JText('SUBMITTING_QUICK_REPLY')]);

            //update the post area content
            var paramString = 'tmpl=component&ajax_request=1';
            var frm = $('jfusionQuickReply' + JFusion.articleId);
            for (i = 0; i < frm.elements.length; i++) {
                if (frm.elements[i].type == "select-one") {
                    var value = frm.elements[i].options[frm.elements[i].selectedIndex].get('value');
                    if (value) {
                        paramString = paramString + '&' + frm.elements[i].name + '=' + value;
                    }
                } else {
                    var id = frm.elements[i].get('value');
                    paramString = paramString + '&' + frm.elements[i].name + '=' + id;
                    if (frm.elements[i].get('id') == 'threadid') {
                        JFusion.threadid = id;
                    }
                }
            }

            JFusion.updatePostArea.post(paramString);
        });
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
    JFusion.threadid = id;
    JFusion.updatePostArea.post('tmpl=component&ajax_request=1&dbtask=update_posts&threadid=' + JFusion.threadid);
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
                        var form = $('JFusionTaskForm');
                        form.articleId.set('value', id);
                        form.dbtask.set('value', task);
                        form.submit();
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
                        var form = $('JFusionTaskForm');
                        form.articleId.set('value', id);
                        form.dbtask.set('value', 'create_thread');
                        form.submit();
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

    JFusion.buttonArea = $('jfusionButtonArea' + id);

    var performTask = new Request.JSON({url: url ,
        onSuccess: function(JSONobject) {
            JFusion.updateContent(JSONobject);
        },
        method: 'post'
    });
    performTask.post('tmpl=component&ajax_request=1&dbtask=' + task + '&threadid=' + JFusion.threadid + '&articleId=' + id + vars);

    var jfusionDebugContainer = $('jfusionDebugContainer' + id);
};

JFusion.toggleDiscussionVisibility = function() {
    var override = arguments[0];
    var discusslink = arguments[1];
    var showdiscussion = '';
    var discussion = $('discussion');
    if (discussion) {
        var jfusionBtnShowreplies = $('jfusionBtnShowreplies' + JFusion.articleId);
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
        var setdiscussionvisibility;
        setdiscussionvisibility = new Request.HTML({
            url: JFusion.articleUrl,
            method: 'get',
            onComplete: function () {
                if (discusslink !== undefined) {
                    window.location = discusslink;
                }
            }
        });
        setdiscussionvisibility.post('tmpl=component&ajax_request=1&show_discussion=' + showdiscussion);
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