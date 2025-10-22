class Wp_Sdtrk_Decrypter {

	/**
	* Constructor
	*/
	constructor() {
		this.localizedData = wp_sdtrk_decrypter;
		this.decryptedData = false;
		this.encryptedData = this.paramsToObject();
	}

	/**
	* Convert all GET-Params to Object
	* @return  {Array} The key-value-pairs
	*/
	paramsToObject() {
		var parameters = new URLSearchParams(window.location.search);
		const result = {}
		for (const [key, value] of parameters) { // each 'entry' is a [key, value] tupple
			result[key] = value;
		}
		return result;
	}

	/**
	* Returns the assigned services
	* @return  {Array} The services
	*/
	get_Services() {
		return this.localizedData.services;
	}

	/**
	* Checks if there are any decryptors set
	* @return  {Boolean} If the decryptor has services
	*/
	has_Services() {
		return (this.get_Services().length > 0) ? true : false;
	}

	/**
	* Get encrypted data
	* @return  {Array} The encrypted data
	*/
	getEncryptedData() {
		return this.encryptedData;
	}

	/**
	* Get decrypted data
	* @return  {Array} The decrypted data
	*/
	getDecryptedData() {
		return this.decryptedData;
	}

	/**
	* Set decrypted data
	* @param {Array} data The decrypted data
	*/
	setDecryptedData(data) {
		this.decryptedData = data;
	}


	/**
	* Decrypt data on server if needed
	*/
	decrypt() {
		//if the data is not already decrypted
		if (this.getDecryptedData() === false) {
			//if there are decryption services set
			if (this.has_Services()) {
				//Payload
				var dataJSON = {};
				dataJSON["action"] = 'wp_sdtrk_handle_public_ajax_callback';
				dataJSON["func"] = 'decryptData';
				dataJSON["data"] = { data: this.getEncryptedData(), meta: this.get_Services() };
				dataJSON['_nonce'] = wp_sdtrk_engine._nonce; //comes from wp
				this.decryptOnServer(dataJSON, this).then(function (decrypter) {
					wp_sdtrk_startEngine(decrypter);
				}).catch(function (err) {
					console.log(err);
				})
			}
			//if there are no services use encrypted data as decrypted data
			else {
				this.setDecryptedData(this.getEncryptedData());
				wp_sdtrk_startEngine(this);
			}
		}
	}

	/**
	* Decrypt data on server if needed
	* @param {Object} dataJSON The data which shall be sent to server
	* @param {Wp_Sdtrk_Decrypter} decrypter The reference of the trigger decrypter
	*/
	decryptOnServer(dataJSON, decrypter) {
		return new Promise(function (resolve, reject) {
			jQuery.ajax({
				cache: false,
				type: "POST",
				url: wp_sdtrk_engine.ajax_url, //comes from wp
				data: dataJSON,
				success: function (response) {
					decrypter.setDecryptedData(JSON.parse(response).data);
					resolve(decrypter);
				},
				error: function (xhr, status, error) {
					reject(error);
				}
			});
		});
	}
}