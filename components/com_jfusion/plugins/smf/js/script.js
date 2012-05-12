function jfusion_doQuote(messageid, cur_session_id)
{
	if (quickReplyCollapsed)
		window.location.href = jf_scripturl + "&action=post;quote=" + messageid + ";topic=" + smf_topic + "." + smf_start + ";sesc=" + cur_session_id;
	else
	{

		if (window.XMLHttpRequest)
		{
			if (typeof window.ajax_indicator == "function")
				ajax_indicator(true);
			getXMLDocument(jf_scripturl + "&action=quotefast;quote=" + messageid + ";sesc=" + cur_session_id + ";xml", onDocReceived);
		}
		else
			reqWin(jf_scripturl + "&action=quotefast;quote=" + messageid + ";sesc=" + cur_session_id, 240, 90);

		if (navigator.appName == "Microsoft Internet Explorer")
			window.location.hash = "quickreply";
		else
			window.location.hash = "#quickreply";
	}
}

function jfusion_modify_msg(msg_id, cur_session_id)
{
	if (!window.XMLHttpRequest)
		return;
	if (typeof(window.opera) != "undefined")
	{
		var test = new XMLHttpRequest();
		if (typeof(test.setRequestHeader) != "function")
			return;
	}
	if (in_edit_mode == 1)
		modify_cancel();
	in_edit_mode = 1;
	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);
	getXMLDocument(jf_scripturl + '&action=quotefast;quote=' + msg_id + ';sesc=' + cur_session_id + ';modify;xml', onDocReceived_modify);
}

function jfusion_modify_save(cur_session_id)
{
	if (!in_edit_mode)
		return true;

	var x = new Array();
	x[x.length] = 'subject=' + escape(textToEntities(document.forms.quickModForm['subject'].value.replace(/&#/g, "&#38;#"))).replace(/\+/g, "%2B");
	x[x.length] = 'message=' + escape(textToEntities(document.forms.quickModForm['message'].value.replace(/&#/g, "&#38;#"))).replace(/\+/g, "%2B");
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements['topic'].value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements['msg'].value);

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);

	sendXMLDocument(jf_scripturl + "&action=jsmodify;topic=" + smf_topic + ";sesc=" + cur_session_id + ";xml", x.join("&"), modify_done);

	return false;
}