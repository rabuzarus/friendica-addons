
{{if !$cron_run}}
	<p><strong>Warning: The cron job has not yet been executed. If this message is still there after some time (usually 10 minutes), this means that autosubscribe and autoaccept will not work.</strong></p>
{{/if}}

{{* bosh proxy *}}
<label for="jsxc-proxy">Activate BOSH proxy</label>
<input id="jsxc-proxy" type="checkbox" name="jsxc-proxy" value="1" {{$is_bosh_proxy}} /><br />

{{* bosh address *}}
<p><label for="jsxc-address">Adress of the default BOSH proxy. If enabled it overrides the user settings:</label><br />
<input id="jsxc-address" type="text" name="jsxc-address" value="{{$bosh_address}}" /></p>

{{* default server address *}}
<p><label for="jsxc-server">Adress of the default jabber server:</label><br />
<input id="jsxc-server" type="text" name="jsxc-server" value="{{$default_server}}" /></p>

{{* default user name to friendica nickname *}}
<label for="jsxc-user">Set the default username to the nickname:</label>
 <input id="jsxc-user" type="checkbox" name="jsxc-defaultuser" value="1" {{$is_default_user}} /><br />

{{* info text field *}}
<p><label for="jsxc-infotext">Info text to help users with configuration (important if you want to provide your own BOSH host!):</label><br />
<textarea id="jsxc-infotext" name="jsxc-infotext" rows="5" cols="50">{{$info_text}}</textarea></p>

{{* submit button *}}
<input type="submit" name="jsxc-admin-settings" value="OK" />
