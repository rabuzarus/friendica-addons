
<span id="settings_jsxc_inflated" class="settings-block fakelink" style="display: block;" onclick="openClose('settings_jsxc_expanded'); openClose('settings_jsxc_inflated');">
	<h3>{{$heading}}</h3>
</span>

<div id="settings_jsxc_expanded" class="settings-block" style="display: none;">
	<span class="fakelink" onclick="openClose('settings_jsxc_expanded'); openClose('settings_jsxc_inflated');">
		<h3>{{$heading}}</h3>
	</span>

	<label for="jsxc-activate">{{$activate_label}}</label>
	<input id="jsxc-activate" type="checkbox" name="jsxc-activate" value="1" {{$activated}} />
	<br />

	<label for="jsxc-dont-insertchat">{{$no_insert_label}}</label>
	<input id="jsxc-dont-insertchat" type="checkbox" name="jsxc-dont-insertchat" value="1" {{$insertchat}} />
	<br />

	<label for="jsxc-username">{{$username_label}}</label>
	<input id="jsxc-username" type="text" name="jsxc-username" value="{{$username}}" />
	<br />

	<label for="jsxc-server">{{$server_label}}</label>
	<input id="jsxc-server" type="text" name="jsxc-server" value="{{$server}}" />
	<br />

	{{if $defaultbosh}}
		<label for="jsxc-bosh">{{$bosh_label}}</label>
		<input id="jsxc-bosh" type="text" name="jsxc-bosh" value="{{$bosh}}" />
		<br />
	{{/if}}


	<label for="jsxc-password">{{$password_label}}</label>
	<input type="hidden" id="jsxc-password" name="jsxc-encrypted-password" value="{{$password}}" />
	<input id="jsxc-clear-password" type="password" value="" onchange="jsxc_set_password();" />
	<br />

	<label for="jsxc-encrypt">{{$encrypt_label}}</label>
	<input id="jsxc-encrypt" type="checkbox" name="jsxc-encrypt" onchange="document.getElementById('jsxc-friendica-password').disabled = !this.checked;jsxc_set_password();" value="1" {{$encrypt_checked}} />
	<br />

	<label for="jsxc-friendica-password">{{$f_password_label}}</label>
	<input id="jsxc-friendica-password" name="jsxc-friendica-password" type="password" onchange="jsxc_set_password();" value="" {{$encrypt_disabled}} />
	<br />

	<label for="jsxc-autoapprove">{{$autoapprove_label}}</label>
	<input id="jsxc-autoapprove" type="checkbox" name="jsxc-autoapprove" value="1" {{$autoapprove}} />
	<br />

	<label for="jsxc-autosubscribe">{{$autosubscribe_label}}</label>
	<input id="jsxc-autosubscribe" type="checkbox" name="jsxc-autosubscribe" value="1" {{$autosubscribe}} />
	<br />

	<label for="jsxc-purge">{{$purge_label}}</label>
	<input id="jsxc-purge" type="checkbox" name="jsxc-purge" value="1" />
	<br />

	{{if $info_text}}
		<br />Configuration help:<p style="margin-left:2em;">{{$info_text}}</p>
	{{/if}}

	<br />Status:<p style="margin-left:2em;">Addon knows {{$address_cnt}} Jabber addresses of {{$address_cnt}} Friendica contacts (takes some time, usually 10 minutes, to update).</p>
	<input type="submit" name="jsxc-submit" value="{{$submit}}" />
	<input type="button" value="{{$add_contact}}" onclick="jsxc_addon_subscribe();" />

</div>
