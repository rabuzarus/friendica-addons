
$(document).ready(function() {
	$(document).on("click", "#login-submit-button", function(){
		var pass = document.getElementById("id_password").value;
		jsxc_addon_set_client_secret(pass);
	});
});
