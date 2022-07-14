class Wp_Sdtrk_Decrypter {

	constructor() {
		this.init = false;
		this.services = wp_sdtrk_decrypter_config.services;
		this.decryptedData = false;
		this.encryptedData = false;
	}

	initialize() {
		this.encryptedData = this.paramsToObject();
		this.decrypt();
	}

	// check if the ajax has been finished
	isInitialized() {
		return this.init;
	}

	//Check if GET-Parameters are encrypted
	isEncrypted() {
		if (this.services.length > 0) {
			return true;
		}
	}

	//Return encrypted data
	getEncryptedData() {
		return this.encryptedData;
	}

	//Return decrypted data
	getDecryptedData() {
		return this.decryptedData;
	}

	//Sends an Ajax-Request to Server
	decrypt() {
		if (this.decryptedData === false) {
			if (this.isEncrypted()) {
				var result = false;
				//Payload
				var dataJSON = {};
				dataJSON["action"] = 'wp_sdtrk_handleAjaxCallback';
				dataJSON["func"] = 'decryptData';
				dataJSON["data"] = this.encryptedData;
				dataJSON["meta"] = this.services;
				dataJSON['_nonce'] = wp_sdtrk._nonce;
				this.decryptOnServer(dataJSON, this).then(function(response) {
					wp_sdtrk_startTracker();
				}).catch(function(err) {
					console.log(err);
				})
			}
			else {
				this.decryptedData = this.encryptedData;
				this.init = true;
				wp_sdtrk_startTracker();
			}
		}
	}

	//Decrypt on server and get data back
	decryptOnServer(dataJSON, decrypter) {
		return new Promise(function(resolve, reject) {
			jQuery.ajax({
				cache: false,
				type: "POST",
				url: wp_sdtrk.ajax_url,
				data: dataJSON,
				success: function(response) {
					decrypter.decryptedData = JSON.parse(response).data;
					decrypter.init = true;
					resolve(response);
				},
				error: function(xhr, status, error) {
					reject(err);
				}
			});
		});
	}

	//Convert all GET-Params to object
	paramsToObject() {
		var parameters = new URLSearchParams(window.location.search);
		const result = {}
		for (const [key, value] of parameters) { // each 'entry' is a [key, value] tupple
			result[key] = value;
		}
		return result;
	}
}