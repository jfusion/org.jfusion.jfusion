if('undefined'===typeof JFusion) {
    var JFusion = {};
    JFusion.Text = [];
    JFusion.jumptoDiscussion = true;
    JFusion.messageArea = false;
    JFusion.ajaxMessageSlide = false;
    JFusion.postPagination = false;
    JFusion.buttonArea = false;
    JFusion.delayHiding = false;
    JFusion.confirmationBoxSlides = [];

    JFusion.updatePostArea = false;
    JFusion.postArea = false;
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
    key = key.toUpperCase();
    if (this.Text[key]) {
        key = this.Text[key];
    }
    return key;
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

    JFusion.postArea = $('jfusionPostArea');
    JFusion.postPagination = $('jfusionPostPagination');
    JFusion.buttonArea = $('jfusionButtonArea' + JFusion.articleId);

    // this code will send a data object via a GET request and alert the retrieved data.

    JFusion.updatePostArea = new Request.JSON({url: JFusion.articleUrl ,
        onSuccess: function(JSONobject) {
            JFusion.updateContent(JSONobject);
        }, onError: function(JSONobject) {
            JFusion.showMessage(JSONobject, 'Error');

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
}

JFusion.updateContent = function(JSONobject) {
    if (JSONobject.status) {
        //update the post area with the updated content
        if (JFusion.postArea) {
            JFusion.postArea.innerHTML = JSONobject.posts;
        }
        if (JFusion.buttonArea) {
            JFusion.buttonArea.innerHTML = JSONobject.buttons;
        }
        if (JFusion.enablePagination && JFusion.postPagination) {
            JFusion.postPagination.innerHTML = JSONobject.pagination;
        }

        var submittedPostId = $('submittedPostId');
        var quickReply = $('quickReply');
        if (submittedPostId) {
            JFusion.highlightPost('post' + submittedPostId.innerHTML);

            //empty the quick reply form
            quickReply.value = '';

            //remove the preview iframe if exists
            if ($('markItUpQuickReply')) {
                jQuery.markItUp({ call: 'previewClose' });
            }
            JFusion.showMessage(JSONobject.message, 'Success');
            JFusion.hideMessage();
        } else if ($('moderatedPostId')) {
            //empty the quick reply form
            quickReply.value = '';
            JFusion.showMessage(JSONobject.message, 'Success');
            JFusion.hideMessage();
        }
    } else {
        JFusion.showMessage(JSONobject.message, 'Error');
    }
    var jfusionDebugContainer = $('jfusionDebugContainer' + JFusion.articleId);
    if (jfusionDebugContainer) {
        jfusionDebugContainer.innerHTML = JSONobject.debug;
    }
}

JFusion.initializeConfirmationBoxes = function () {
    var i;
	var containers = $$('div.jfusionButtonConfirmationBox');
	if (containers) {
		for (i = 0; i < containers.length; i++) {
			var divId = containers[i].id;
            if (typeof (JFusion.confirmationBoxSlides[divId]) != 'object') {
                JFusion.confirmationBoxSlides[divId] = new Fx.Slide(divId);
                JFusion.confirmationBoxSlides[divId].hide();
            }
		}
	}
}

JFusion.prepareAjax = function() {
    var i;
    var submitpost = $('submitpost');

    if (submitpost) {
        //add the submitpost function
        submitpost.addEvent('click', function (e) {
            //show a loading
            JFusion.showMessage(JFusion.JText('SUBMITTING_QUICK_REPLY'), 'Loading');

            //update the post area content
            var paramString = 'tmpl=component&ajax_request=1';
            var frm = $('jfusionQuickReply' + JFusion.articleId);
            for (i = 0; i < frm.elements.length; i++) {
                if (frm.elements[i].type == "select-one") {
                    if (frm.elements[i].options[frm.elements[i].selectedIndex].value) {
                        paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].options[frm.elements[i].selectedIndex].value;
                    }
                } else {
                    paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].value;
                    if (frm.elements[i].id == 'threadid') {
                        JFusion.threadid = frm.elements[i].value;
                    }
                }
            }

            JFusion.updatePostArea.post(paramString);
        });
    }
}

JFusion.showMessage = function(msg, type) {
    //stop a slideOut if pending
    if (JFusion.delayHiding) {
        clearTimeout(JFusion.delayHiding);
    }

    $('jfusionMessage').innerHTML = msg;
    JFusion.messageArea.setAttribute('class', 'jfusion' + type + 'Message');
    if (type == 'Error') {
        window.location = '#jfusionMessageArea';
    }

    JFusion.ajaxMessageSlide.slideIn();
}

JFusion.hideMessage = function() {
    JFusion.delayHiding = setTimeout('JFusion.ajaxMessageSlide.slideOut()', 5000);
}

JFusion.highlightPost = function(postid) {
    var post = $(postid);
    if (post) {
        post.setStyle('border', '2px solid #FCFC33');
        (function () { post.setStyle('border', '2px solid #afafaf'); }).delay(5000);

        if (JFusion.enableJumpto) {
            window.location = '#' + postid;
        }
    }

}

JFusion.refreshPosts = function(id) {
    JFusion.threadid = id;
    JFusion.updatePostArea.post('tmpl=component&ajax_request=1&dbtask=update_posts&threadid=' + JFusion.threadid);
}

JFusion.confirmThreadAction = function(id, task, vars, url) {
	var container = $('jfusionButtonConfirmationBox' + id);
	//clear anything already there
	container.innerHTML = '';
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
                    form.articleId.value = id;
                    form.dbtask.value = task;
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
                    form.articleId.value = id;
                    form.dbtask.value = 'create_thread';
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
    JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + id].slideIn();
}

JFusion.clearConfirmationBox = function(id) {
	var container = $('jfusionButtonConfirmationBox' + id);
	if (container) {
		container.innerHTML = '';
        JFusion.confirmationBoxSlides['jfusionButtonConfirmationBox' + id].hide();
	}
}

JFusion.submitAjaxRequest = function (id, task, vars, url) {
    JFusion.clearConfirmationBox(id);

    var performTask;

    JFusion.buttonArea = $('jfusionButtonArea' + id);

    performTask = new Request.JSON({url: url ,
        onSuccess: function(JSONobject) {
            JFusion.updateContent(JSONobject);
        },
        method: 'post'
    });
    performTask.post('tmpl=component&ajax_request=1&dbtask=' + task + '&threadid=' + JFusion.threadid + '&articleId=' + id + vars);

    var jfusionDebugContainer = $('jfusionDebugContainer' + id);
}

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
            jfusionBtnShowreplies.innerHTML = JFusion.JText('HIDE_REPLIES');
            showdiscussion = 1;
        } else {
            discussion.style.display = 'none';
            jfusionBtnShowreplies.innerHTML = JFusion.JText('SHOW_REPLIES');
            showdiscussion = 0;
        }
        if (override !== null) {
            showdiscussion = override;
        }
        var setdiscussionvisibility;
        setdiscussionvisibility = new Request.HTML({
            url: JFusion.articleUrl,
            method: 'get',
            onComplete: function () {
                if (discusslink!==undefined) {
                    window.location = discusslink;
                }
            }
        });
        setdiscussionvisibility.post('tmpl=component&ajax_request=1&show_discussion=' + showdiscussion);
    } else {
        if (discusslink!==undefined) {
            window.location = discusslink;
        }
    }
}

JFusion.quote = function(pid) {
    var quickReply = $('quickReply');
    quickReply.value = $('originalText' + pid).innerHTML;
    window.location = '#jfusionQuickReply';
    quickReply.focus();
}