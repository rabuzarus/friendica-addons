
//jQuery(document).ready(function() {
//	encrypt = document.getElementById('jsxc-encrypt').checked;
//	password = document.getElementById('jsxc-password');
//	clear_password = document.getElementById('jsxc-clear-password');
//	if (encrypt) {
//		jsxc_addon_decrypt_password(password.value, function(decrypted_password){
//			clear_password.value = decrypted_password;
//		});
//	}
//	else {
//		clear_password.value = password.value;
//	}
//});

function jappixmini_set_password() {
	encrypt = document.getElementById('jsxc-encrypt').checked;
	password = document.getElementById('jsxc-password');
	clear_password = document.getElementById('jsxc-clear-password');

	if (encrypt) {
		friendica_password = document.getElementById('jsxc-friendica-password');

		if (friendica_password) {
			jsxc_addon_set_client_secret(friendica_password.value);
			jsxc_addon_encrypt_password(clear_password.value, function(encrypted_password){
				password.value = encrypted_password;
			});
		}
	}
	else {
		password.value = clear_password.value;
	}
}