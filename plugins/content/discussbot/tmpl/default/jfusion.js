var ajaxMessageSlide, ajaxErrorSlide, confirmationBoxSlides, updatepostarea, threadid, updatebuttons, postarea, ajaxstatusholder;
var updatepagination = null;

function initializeDiscussbot() {
    if (jfdb_jumpto_discussion) {
        window.location = '#discussion';
    }

    //only initiate if the div container exists and if the var has not been declared Fx.Slide
    if ($('jfusionMessageArea') && typeof (ajaxMessageSlide) != 'object') {
        ajaxMessageSlide = new Fx.Slide('jfusionMessageArea');
        ajaxMessageSlide.hide();
    }

    if ($('jfusionErrorArea') && typeof (ajaxErrorSlide) != 'object') {
        ajaxErrorSlide = new Fx.Slide('jfusionErrorArea', {
            onComplete: function () {
                ajaxMessageSlide.slideOut();
            }
        });
        ajaxErrorSlide.hide();
    }

    //only initiate if the div container exists and if the var has not been declared Fx.Slide
	if (!(confirmationBoxSlides instanceof Array)) {
		confirmationBoxSlides = [];
	}

    if ($('jfusionButtonConfirmationBox' + jfdb_article_id) && typeof (confirmationBoxSlides['jfusionButtonConfirmationBox' + jfdb_article_id]) != 'object') {
        confirmationBoxSlides['jfusionButtonConfirmationBox' + jfdb_article_id] = new Fx.Slide('jfusionButtonConfirmationBox' + jfdb_article_id);
        confirmationBoxSlides['jfusionButtonConfirmationBox' + jfdb_article_id].hide();
    }

    var url = jfdb_article_url;
    ajaxstatusholder = $('jfusionAjaxStatus');
    postarea = $('jfusionPostArea');

	if (jfdb_enable_pagination) {
        updatepagination = jfdb_isJ16 ? new Request.HTML({
            url: url,
            update: $('jfusionPostPagination'),
            method: 'post'
        }) : new Ajax(url, {
            update: $('jfusionPostPagination'),
            method: 'post'
        });
    }

    updatebuttons = jfdb_isJ16 ? new Request.HTML({
        url: url,
        update: $('jfusionButtonArea' + jfdb_article_id),
        method: 'post'
    }) : new Ajax(url, {
        update: $('jfusionButtonArea' + jfdb_article_id),
        method: 'post'
    });

    updatepostarea = jfdb_isJ16 ? new Request.HTML({
        url: url,
        update: ajaxstatusholder,
        method: 'post',

        onComplete: function () {
            var ajaxstatus = ajaxstatusholder.innerHTML, rg = new RegExp(JFDB_DISCUSSBOT_ERROR);
            if (ajaxstatus.search(rg) == -1) {

                //update the post area with the updated content
                postarea.innerHTML = ajaxstatus;

                //reset the status area
                ajaxstatusholder.innerHTML = '';

                if ($('submittedPostId')) {
                    highlightPost('post' + $('submittedPostId').innerHTML);

                    //empty the quick reply form
                    $('quickReply').value = '';

                    //remove the preview iframe if exists
                    if ($('markItUpQuickReply')) {
                        jQuery.markItUp({ call: 'previewClose' });
                    }

                    showMessage(JFDB_SUCCESSFUL_POST, 'Success');
                    hideMessage();
                } else if ($('moderatedPostId')) {
                    //empty the quick reply form
                    $('quickReply').value = '';

                    showMessage(JFDB_SUCCESSFUL_POST_MODERATED, 'Success');
                    hideMessage();
                }

                //update buttons
                updatebuttons.post('tmpl=component&dbtask=update_buttons&ajax_request=1&threadid=' + threadid);

                if (jfdb_enable_ajax) {
                    //update pagination
                    var frm = $('jfusionPaginationForm');
                    var paramString = 'tmpl=component&dbtask=update_pagination&ajax_request=1&threadid=' + threadid;
                    if (frm) {
                        for (var i=0; i<frm.elements.length; i++) {
                            if(frm.elements[i].type=="select-one") {
                                if(frm.elements[i].options[frm.elements[i].selectedIndex].value) {
                                    paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].options[frm.elements[i].selectedIndex].value;
                                }
                            } else {
                                paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].value;
                            }
                        }
                    }
                    if (jfdb_enable_pagination) {
                        updatepagination.post(paramString);
                    }
                }
            } else {
                showMessage(ajaxstatus,'Error');
                window.location='#jfusionMessageArea';
            }
        }
    }) : new Ajax(url, {
        update: ajaxstatusholder,
        method: 'post',

        onComplete: function() {
            var ajaxstatus = ajaxstatusholder.innerHTML;
            var rg = new RegExp(JFDB_DISCUSSBOT_ERROR);
            if (ajaxstatus.search(rg) == -1) {

                //update the post area with the updated content
                postarea.innerHTML = ajaxstatus;

                //reset the status area
                ajaxstatusholder.innerHTML = '';

                if($('submittedPostId')) {
                    highlightPost('post' + $('submittedPostId').innerHTML);

                    //empty the quick reply form
                    $('quickReply').value = '';

                    //remove the preview iframe if exists
                    if($('markItUpQuickReply')) {
                        jQuery.markItUp({ call: 'previewClose' });
                    }

                    showMessage(JFDB_SUCCESSFUL_POST,'Success');
                    hideMessage();
                } else if ($('moderatedPostId')){
                    //empty the quick reply form
                    $('quickReply').value = '';

                    showMessage(JFDB_SUCCESSFUL_POST_MODERATED,'Success');
                    hideMessage();
                }

                //update buttons
                updatebuttons.request('tmpl=component&dbtask=update_buttons&ajax_request=1&threadid=' + threadid);

                if (jfdb_enable_ajax) {
                    //update pagination
                    var frm = $('jfusionPaginationForm');
                    var paramString = 'tmpl=component&dbtask=update_pagination&ajax_request=1&threadid=' + threadid;
                    if(frm) {
                        for(var i=0; i<frm.elements.length; i++){
                            if(frm.elements[i].type=="select-one"){
                                if(frm.elements[i].options[frm.elements[i].selectedIndex].value) {
                                    paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].options[frm.elements[i].selectedIndex].value;
                                }
                            } else {
                                paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].value;
                            }
                        }
                    }
                    if(jfdb_enable_pagination) {
                        updatepagination.request(paramString);
                    }
                }
            } else {
                showMessage(ajaxstatus,'Error');
                window.location='#jfusionMessageArea';
            }
        }
    });

    //load markItUp
    if (typeof jfdb_load_markitup != 'undefined') {
        if(jQuery('#quickReply')) {
            jQuery('#quickReply').markItUp(mySettings);
        }
    }

    //get ajax ready for submission
    if( jfdb_enable_ajax && $('jfusionMessageArea')) {
		prepareAjax();
    }
}

function updateAjax() {
    var ajaxstatus = ajaxstatusholder.innerHTML;
    var rg = new RegExp(JFDB_DISCUSSBOT_ERROR);
    if (ajaxstatus.search(rg) == -1) {

        //update the post area with the updated content
        postarea.innerHTML = ajaxstatus;

        //reset the status area
        ajaxstatusholder.innerHTML = '';

        if($('submittedPostId')) {
            var postid = $('submittedPostId').innerHTML;
            postid = 'post' + postid;
            highlightPost(postid);

            //empty the quick reply form
            $('quickReply').value = '';

            //remove the preview iframe if exists
            if($('markItUpQuickReply')) {
                jQuery.markItUp({ call: 'previewClose' });
            }

            showMessage(JFDB_SUCCESSFUL_POST,'Success');
            hideMessage();
        } else if ($('moderatedPostId')){
            //empty the quick reply form
            $('quickReply').value = '';

            showMessage(JFDB_SUCCESSFUL_POST_MODERATED,'Success');
            hideMessage();
        }

        //update buttons
        updatebuttons.request('tmpl=component&dbtask=update_buttons&ajax_request=1&threadid=' + threadid);

        if (jfdb_enable_ajax) {
            //update pagination
            var frm = $('jfusionPaginationForm');
            var paramString = 'tmpl=component&dbtask=update_pagination&ajax_request=1&threadid=' + threadid;
            if(frm) {
                for(var i=0; i<frm.elements.length; i++){
                    if(frm.elements[i].type=="select-one"){
                        if(frm.elements[i].options[frm.elements[i].selectedIndex].value) {
                            paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].options[frm.elements[i].selectedIndex].value;
                        }
                    } else {
                        paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].value;
                    }
                }
            }
            if(jfdb_enable_pagination) {
                updatepagination.request(paramString);
            }
        }
    } else {
        showMessage(ajaxstatus,'Error');
        window.location='#jfusionMessageArea';
    }
}

function initializeConfirmationBoxes() {
	if (!(confirmationBoxSlides instanceof Array)) {
		confirmationBoxSlides = [];
	}
	var containers = $$('div.jfusionButtonConfirmationBox');
	if (containers) {
		for(var i=0; i < containers.length; i++){
			var divId = containers[i].id;
            if (typeof (confirmationBoxSlides[divId]) != 'object') {
                confirmationBoxSlides[divId] = new Fx.Slide(divId);
                confirmationBoxSlides[divId].hide();
            }
		}
	}
}

function prepareAjax()
{
    var submitpost = $('submitpost');

    //add the submitpost function
    submitpost.addEvent('click', function(e) {
        //show a loading
        showMessage(JFDB_SUBMITTING_QUICK_REPLY,'Loading');

        //update the post area content
        var paramString = 'tmpl=component&ajax_request=1';
        var frm =$('jfusionQuickReply' + jfdb_article_id);
        for(var i=0; i<frm.elements.length; i++){
            if(frm.elements[i].type == "select-one"){
                if(frm.elements[i].options[frm.elements[i].selectedIndex].value) {
                    paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].options[frm.elements[i].selectedIndex].value;
                }
            } else {
                paramString = paramString + '&' + frm.elements[i].name + '=' + frm.elements[i].value;
                
                if (frm.elements[i].id == 'threadid') {
                    threadid = frm.elements[i].value;
                }
            }
        }

        if (jfdb_isJ16) {
            updatepostarea.post(paramString);
        } else {
            updatepostarea.request(paramString);
        }
    });
}

var delayHiding;
function showMessage(msg, type)
{
    //stop a slideOut if pending
    if(delayHiding) {
        clearTimeout(delayHiding);
    }
    var msgDiv,msgArea;
    if (type == 'Error') {
        msgDiv = $('jfusionErrorMessage');
        msgArea = $('jfusionErrorArea');
    } else {
        msgDiv = $('jfusionMessage');
        msgArea = $('jfusionMessageArea');
    }
    
    ajaxErrorSlide.hide();
    
    msgDiv.innerHTML = msg;
    msgArea.setAttribute('class','jfusion'+type+'Message');

    if (type == 'Error') {
        ajaxErrorSlide.slideIn();
        //ajaxMessageSlide.slideOut();
    } else {
        ajaxMessageSlide.slideIn();
        //ajaxErrorSlide.slideOut();
    }
}

function hideMessage()
{
	delayHiding = setTimeout('ajaxMessageSlide.slideOut()',5000);
}

function highlightPost(postid)
{
    $(postid).setStyle('border','2px solid #FCFC33');
    (function() { $(postid).setStyle('border','2px solid #afafaf'); } ).delay(5000);

    if (jfdb_enable_jumpto) {
        window.location='#' + postid;
    }
}

function refreshPosts(id)
{
	threadid = id;
    if (jfdb_isJ16) {
        updatepostarea.post('tmpl=component&ajax_request=1&dbtask=update_posts&threadid=' + threadid);
    } else {
        updatepostarea.request('tmpl=component&ajax_request=1&dbtask=update_posts&threadid=' + threadid);
    }
}

function confirmThreadAction(id,task,vars,url) {
	var container = $('jfusionButtonConfirmationBox' + id);
	//clear anything already there
	container.innerHTML = '';
	var msg = '';
    if (task=='create_thread') {
        msg = JFDB_CONFIRM_THREAD_CREATION;
    } else if (task=='unpublish_discussion') {
        msg = JFDB_CONFIRM_UNPUBLISH_DISCUSSION;
    } else if (task=='publish_discussion') {
        msg = JFDB_CONFIRM_PUBLISH_DISCUSSION;
    }
    
    //set the confirmation text
    if (jfdb_isJ16) {
		new Element('span', {
			text: msg,
			styles: {
					fontWeight: "bold",
					display: "block",
					fontSize: "13px",
					padding: "5px"
			}
		}).inject(container);
    } else {
		new Element('span', {
			styles: {
					fontWeight: "bold",
					display: "block",
					fontSize: "13px",
					padding: "5px"
			}
		}).setHTML(msg).inject(container);
    }
	
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
		value: JFDB_BUTTON_CANCEL,
		events: {
			click: function() {
				clearConfirmationBox(id);
			}
		}
	}).inject(divBtnContainer);
	
    //create the buttons
	if (task == 'create_thread') {
		new Element('input', {
			type: 'button',
			'class': 'button',
			value: JFDB_BUTTON_INITIATE,
			styles: {
				marginLeft: '3px'
			},
			events: {
				click: function() {
                    var form = $('JFusionTaskForm');
                    form.articleId.value = id;
                    form.dbtask.value = task;
                    form.submit();
				}
			}
		}).inject(divBtnContainer);
    } else if (task=='publish_discussion') {
		new Element('input', {
			type: 'button',
			'class': 'button',
			value: JFDB_BUTTON_REPUBLISH_DISCUSSION,
			styles: {
				marginLeft: '3px'
			},
			events: {
				click: function() {
					submitAjaxRequest(id,task,vars,url);
				}
			}
		}).inject(divBtnContainer);
		
		new Element('input', {
			type: 'button',
			'class': 'button',
			value: JFDB_BUTTON_PUBLISH_NEW_DISCUSSION,
			styles: {
				marginLeft: '3px'
			},	
			events: {
				click: function() {
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
			value: JFDB_BUTTON_UNPUBLISH_DISCUSSION,
			styles: {
				marginLeft: '3px'
			},
			events: {
				click: function() {
					submitAjaxRequest(id,task,vars,url);
				}
			}
		}).inject(divBtnContainer);
    }
	
    //attach the buttons
    divBtnContainer.inject(container);
	
    //show the message
    confirmationBoxSlides['jfusionButtonConfirmationBox' + id].slideIn();
}

function clearConfirmationBox(id) {
	var container = $('jfusionButtonConfirmationBox' + id);
	if (container) {
		container.innerHTML = '';
		confirmationBoxSlides['jfusionButtonConfirmationBox' + id].hide();
	}
}

function submitAjaxRequest(id, task, vars, url) {
	clearConfirmationBox(id);

    var performTask;

    if (jfdb_isJ16) {
        performTask = new Request.HTML({
            url: url,
            method: 'post',
            update: $('jfusionButtonArea'+id),
            onComplete: function() {
                if ($('discussion')) {
                    updateMainContent.post('tmpl=component&ajax_request=1&dbtask=update_content&threadid=' + threadid);
                } else if (typeof jfdb_debug != 'undefined') {
                    updateDebugInfo.post('tmpl=component&ajax_request=1&dbtask=update_debug_info&articleId='+id+vars);
                }
            }
        });

        performTask.post('tmpl=component&ajax_request=1&dbtask='+task+'&threadid=' + threadid + '&articleId='+id+vars);

    } else {
        performTask = new Ajax(url, {
            method: 'post',
            update: $('jfusionButtonArea'+id),
            onComplete: function() {
                if ($('discussion')) {
                    updateMainContent.request('tmpl=component&ajax_request=1&dbtask=update_content&threadid=' + threadid);
                } else if (typeof jfdb_debug != 'undefined') {
                    updateDebugInfo.request('tmpl=component&ajax_request=1&dbtask=update_debug_info&threadid=' + threadid + '&articleId='+id+vars);
                }
            }
        });
        performTask.request('tmpl=component&ajax_request=1&dbtask='+task+'&threadid=' + threadid + '&articleId='+id+vars);
    }
    var updateDebugInfo = jfdb_isJ16 ? new Request.HTML({
        url: url,
        method: 'post',
        update: $('jfusionDebugContainer' + id)
    }) : new Ajax(url, {
        method: 'post',
        update: $('jfusionDebugContainer' + id)
    });
    
    if (jfdb_isJ16) {
        Request.HTML({
            url: url,
            method: 'post',
            update: $('discussion'),
            onComplete: function() {
                if (task=='unpublish_discussion') {
                    $('discussion').setStyle('display','none');
                } else if (task=='publish_discussion') {
                    initializeDiscussbot();
                    toggleDiscussionVisibility(1);
                }
                
                if (typeof jfdb_debug != 'undefined') {
                    updateDebugInfo.post('tmpl=component&ajax_request=1&dbtask=update_debug_info&threadid=' + threadid + '&articleId='+id+vars);
                }
            }
        });
    } else {
        Ajax(url, {
            method: 'post',
            update: $('discussion'),
            onComplete: function() {
                if (task=='unpublish_discussion') {
                    $('discussion').setStyle('display','none');
                } else if (task=='publish_discussion') {
                    initializeDiscussbot();
                    toggleDiscussionVisibility(1);
                }
                
                if (typeof jfdb_debug != 'undefined') {
                    updateDebugInfo.request('tmpl=component&ajax_request=1&dbtask=update_debug_info&threadid=' + threadid + '&articleId='+id+vars);
                }                        
            }
        });
    }
}

function toggleDiscussionVisibility() {
    var override = arguments[0];
    var discusslink = arguments[1];
    var showdiscussion = '';
    if ($('discussion')) {
        var state = $('discussion').style.display;
        if (state=='none') {
            $('discussion').style.display = 'block';
            $('jfusionBtnShowreplies' + jfdb_article_id).innerHTML = JFDB_HIDE_REPLIES;
            showdiscussion = 1;
        } else {
            $('discussion').style.display = 'none';
            $('jfusionBtnShowreplies' + jfdb_article_id).innerHTML = JFDB_SHOW_REPLIES;
            showdiscussion = 0;
        }
    }

    if (override!==null) {
        showdiscussion = override;
    }
    var setdiscussionvisibility;
    if (jfdb_isJ16) {
        setdiscussionvisibility = new Request.HTML({
            url: jfdb_article_url,
            method: 'get',
            onComplete: function() {
                if (discusslink!==null) {
                    window.location=discusslink;
                }
            }
        });
        setdiscussionvisibility.post('tmpl=component&ajax_request=1&show_discussion='+showdiscussion);
    } else {
        setdiscussionvisibility = new Ajax(jfdb_article_url, {
            method: 'get',
            onComplete: function() {
                if (discusslink!==null) {
                    window.location=discusslink;
                }
            }
        });
        setdiscussionvisibility.request('tmpl=component&ajax_request=1&show_discussion='+showdiscussion);
    }
}

function jfusionQuote(pid) {
    $('quickReply').value = $('originalText'+pid).innerHTML;
    window.location='#jfusionQuickReply';
    $('quickReply').focus();
}