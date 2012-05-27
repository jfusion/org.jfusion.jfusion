function jfusion_doQuote(messageid, cursessionid) {
	if (quickReplyCollapsed) {
		window.location.href = jf_scripturl + "&action=post;quote=" + messageid + ";topic=" + smf_topic + "." + smf_start + ";sesc=" + cursessionid;
    } else {
		if (window.XMLHttpRequest) {
			if (typeof window.ajax_indicator == "function") {
				ajax_indicator(true);
            }
			getXMLDocument(jf_scripturl + "&action=quotefast;quote=" + messageid + ";sesc=" + cursessionid + ";xml", onDocReceived);
		} else {
			reqWin(jf_scripturl + "&action=quotefast;quote=" + messageid + ";sesc=" + cursessionid, 240, 90);
        }
		if (navigator.appName === "Microsoft Internet Explorer") {
			window.location.hash = "quickreply";
        } else {
			window.location.hash = "#quickreply";
        }
	}
}

function jfusion_modify_msg(msgid, cursessionid) {
	if (window.XMLHttpRequest) {
        if (typeof(window.opera) != "undefined") {
            var test = new XMLHttpRequest();
            if (typeof(test.setRequestHeader) != "function") {
                return;
            }
        }
        if (in_edit_mode == 1) {
            modify_cancel();
        }
        in_edit_mode = 1;
        if (typeof window.ajax_indicator == "function") {
            ajax_indicator(true);
        }
        getXMLDocument(jf_scripturl + '&action=quotefast;quote=' + msgid + ';sesc=' + cursessionid + ';modify;xml', onDocReceived_modify);
    }
}

function jfusion_modify_save(cursessionid) {
	if (!in_edit_mode) {
        return true;
    }

	var x = new Array();
	x[x.length] = 'subject=' + escape(textToEntities(document.forms.quickModForm['subject'].value.replace(/&#/g, "&#38;#"))).replace(/\+/g, "%2B");
	x[x.length] = 'message=' + escape(textToEntities(document.forms.quickModForm['message'].value.replace(/&#/g, "&#38;#"))).replace(/\+/g, "%2B");
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements['topic'].value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements['msg'].value);

	if (typeof window.ajax_indicator === "function") {
		ajax_indicator(true);
    }

	sendXMLDocument(jf_scripturl + "&action=jsmodify;topic=" + smf_topic + ";sesc=" + cursessionid + ";xml", x.join("&"), modify_done);
	return false;
}