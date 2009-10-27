var GFC = function() {
	var that = {};
	/**
	 * get user properties from GFC
	 */
	var init = function(securityToken) {

		var req = opensocial.newDataRequest();
		var opt_params = {};
		opt_params[opensocial.DataRequest.PeopleRequestFields.PROFILE_DETAILS] = [
				opensocial.Person.Field.ID,
				opensocial.Person.Field.THUMBNAIL_URL,
				opensocial.Person.Field.PROFILE_URL,
				opensocial.Person.Field.URLS, opensocial.Person.Field.NAME ];
		req.add(req.newFetchPersonRequest('VIEWER', opt_params), 'viewer');
		req.send(initSuccess);

	};

	var initSuccess = function(data) {
		var viewer = data.get('viewer').getData();
		if (viewer) {
			var nameString = viewer.getField("displayName").split(' ');
			var username = nameString.join('');
			var id = viewer.getId();
			profile_id_url = viewer.getField("profileUrl");
			profileurl = viewer.getField(opensocial.Person.Field.URLS)[0]
					.getField('address');
			passwd = getPassword(profile_id_url);
			image_url = viewer.getField("thumbnailUrl");
			var content = '';
			if (FC_USER_ID > 0) {
			  content = [
					 '<img style="width: 32px; height: 32px;" class="avatar avatar-32 photo" align="left" src="',
					 viewer.getField("thumbnailUrl") , '" alt="avatar"/>',
				  '<strong>Hello ', viewer.getField("displayName"),'!</strong><br/>',
						'<a href="#" onclick="google.friendconnect.requestSettings()">Settings</a> | ',
						'<a href="#" onclick="google.friendconnect.requestInvite()">Invite</a> | ',
						'<a href="', FC_LOGOUT_URL, '" onclick="google.friendconnect.requestSignOut()">Sign out</div>'].join('');
			} else {
				content = "Loading...";
			}
			if(FC_ELEMENT_ID) {
				var el = document.getElementById(FC_ELEMENT_ID);
				if(el != null) {
					el.innerHTML = content;
				}
			}

			if (FC_USER_ID == 0) {
				doRequest(id, username, passwd, profileurl, profile_id_url,
						image_url, loadedProfile);
			}
		} else {
			if (document.getElementById(FC_ELEMENT_ID) != null) {
				google.friendconnect.renderSignInButton( {
					id : FC_ELEMENT_ID
				});
			}
		}

	};

	var getElementsByClass = function(node, searchClass, tag) {
		var classElements = new Array();
		var els = node.getElementsByTagName(tag);
		var elsLen = els.length;
		var pattern = new RegExp("\b" + searchClass + "\b");
		for (i = 0, j = 0; i < elsLen; i++) {
			if (pattern.test(els[i].className)) {
				classElements[j] = els[i];
				j++;
			}
		}
		return classElements;
	};

	var fixUrls = function() {
		var anchors = document.getElementsByTagName('a');
		if (anchors != null) {
			for ( var i = 0; i < anchors.length; i++) {
				var href = anchors[i].href;
				if (href.match('action=logout')) {
					anchors[i].onclick = function() {
						google.friendconnect.requestSignOut();
					};
				}
			}
		}
	};

	var getPassword = function(profilestr) {
		var newString = profilestr.split('&');
		if (newString.length < 1)
			return profilestr;
		return newString[1];
	};

	// The call to the SACK library to send an AJAX request to the
	// server side for user logging/creation
	var doRequest = function(id, username, passwd, profileurl, profile_id_url,
			image_url, callback) {
		var mysack = new sack(FC_PLUGIN_URL + "server_code.php");
		mysack.execute = 1;
		mysack.method = 'POST';
		mysack.setVar("uid", id);
		mysack.setVar("username", username);
		mysack.setVar("passwd", passwd);
		mysack.setVar("profileurl", profileurl);
		mysack.setVar("profile_id_url", profile_id_url);
		mysack.setVar("image_url", image_url);
		mysack.onError = function() {
			alert('Ajax error in user')
		};
		mysack.onCompletion = function() {
			callback();
		};
		mysack.runAJAX();
		return true;
	};

	var loadedProfile = function() {
		window.location.reload();
	};

	that.init = init;
	that.fixUrls = fixUrls;
	that.getElementsByClass = getElementsByClass;
	return that;
}();
