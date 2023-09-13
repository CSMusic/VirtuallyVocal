<?php

namespace VirtuallyVocal;


class Api {
    public static $test;
    public static $parameters;
    public static $client_id;
	public static $client_secret;
    public static $vv_token;
    public static $token_timestamp;
    public static $vv_endpoint;
    public static $states_list;


    public static function setTestMode($test)
	{
		self::$test = $test;
	}

    public static function add($key,$value)
	{
		self::$parameters[$key] = $value;
	}
    public static function setClientId($client_id)
	{
		self::$client_id = $client_id;
	}
    public static function setClientSecret($client_secret)
    {
        self::$client_secret = $client_secret;
    }
    public static function vvEndpoint()
	{
		if(self::$test == true) {
			self::$vv_endpoint = "http://stageapi.virtuallyvocal.com/api/";
		} else {
			self::$vv_endpoint = "https://api.virtuallyvocal.com/api/";
		}
		return self::$vv_endpoint;
	}

	public static function vvSetEndpoint($vv_endpoint)
	{
		self::$vv_endpoint = $vv_endpoint;
	}

    public static function getToken()
    {
        if(!self::checkToken()) {
            self::generateToken();
        }
        return self::$vv_token;
    }
    public static function checkToken()
    {
        if(self::$token_timestamp != null) {
            $savedTimestamp = strtotime(self::$token_timestamp);
            $currentTime = time();
            $timeDifference = $currentTime - $savedTimestamp;
            if($timeDifference > 360) { // more than 3 minutes 
                return false; // need to regenerate token
            }
            else {
                return true;
            }
        }
        
        return false; // if token timestamp is null, then token was never generated
    }
    public static function generateToken()
    {
        self::add("clientId", self::$client_id);
        self::add("secret", self::$client_secret);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::vvEndpoint() . "auth/generate-token" );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, count(self::$parameters) );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(self::$parameters) );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        $result = curl_exec($ch);

        

        $decoded = json_decode($result);

        

        if($decoded->data != null) {
            self::$vv_token = $decoded->data;
        }
        self::$parameters = null;
        $currentTimestamp = time();
        self::$token_timestamp = date("Y-m-d H:i:s", $currentTimestamp);
    }
    public static function generatePassword() // generates temporary password that user is prompted to reset upon account creation.
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $length = 12; // 12 characters for password
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public static function getStateValue($state) // converts CS Music two letter state abbreviation into number value for Virtually Vocal.
    {
        if($state == "") {
            return 52; // Value for Other. Don't bother with states list.
        }

        if(self::$states_list == null) {
            self::getStates();
        }
        if(array_key_exists($state, self::$states_list)) {
            return self::$states_list[$state];
        }
        else { // state not found
            return 52; // Value for Other.
        }

    }
    public static function checkUserExists($email) {

        if(!self::checkToken()) {
            self::generateToken();
        }
        $encoded_email = str_replace('@', '%40', $email);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::vvEndpoint() . "users/check-user/" . $encoded_email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_FAILONERROR,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . self::$vv_token // Set the Bearer token in the header
        ]);

        $result = curl_exec($ch);

        $json_result = json_decode($result);

        if($json_result->status == 200) {
            return $json_result;
        }
        return false;
    }
    public static function unsubscribe($email)
    {
        if(!self::checkToken()) {
            self::generateToken();
        }
        $encoded_email = str_replace('@', '%40', $email);
        $ch = curl_init();
        $endpoint = self::vvEndpoint() . "subscription/unsubscribe/" . $encoded_email;
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_FAILONERROR,true);
        curl_setopt($ch, CURLOPT_POST, 1 );
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . self::$vv_token // Set the Bearer token in the header
        ]);

        $result = curl_exec($ch);
        $json_result = json_decode($result);
        if($json_result->status == 200) {
            return $json_result;
        }
        return false;
    }
    public static function registerUser(object $Account, $role = null)
	{
        if($role == "teacher") {
            $role = "affilatePartner"; // teacher
        }
        else {
            $role = "vocal"; // student
        }

        $existing_user = self::checkUserExists($Account->email);

        if($existing_user == false) { // these fields are required by Virtually Vocal, so set if not found on existing user or on account.
            if(!$Account->_profile_address_street) {
                $Account->_profile_address_street = "unknown";
            }
            if(!$Account->_profile_address_city) {
                $Account->_profile_address_city = "unknown";
            }
            if(!$Account->_profile_address_postalcode) {
                $Account->_profile_address_postalcode = "00000";
            }
            if(!$Account->_profile_address_apt) {
                $Account->_profile_address_apt = ""; // does not accept null
            }
        }
        else {
            $existing_user = $existing_user->data;
        }
        self::add("firstName", $existing_user !== false ? $existing_user->firstName : $Account->first_name);
        self::add("lastName", $existing_user !== false ? $existing_user->lastName : $Account->last_name);
        self::add("username", $existing_user !== false ? $existing_user->username : $Account->email);
        $password = self::generatePassword();
        self::add("password", $password); // this will only update if creating a new user. Ignored if updating a current user. 
        self::add("address1", $existing_user !== false ? $existing_user->address1 : $Account->_profile_address_street);
        self::add("address2", $existing_user !== false ? $existing_user->address2 : $Account->_profile_address_apt);
        self::add("city", $existing_user !== false ? $existing_user->city : $Account->_profile_address_city);
        $stateVal = $existing_user !== false ? $existing_user->stateId : self::getStateValue($Account->_profile_address_state);
        self::add("stateId", $stateVal);
        self::add("zipcode", $existing_user !== false ? $existing_user->zipcode : $Account->_profile_address_postalcode);
        self::add("email", $Account->email);
        self::add("rbSubscriptionId", "00000");
        self::add("source", "CSMUSIC");
        self::add("role", $role);
        if(!self::checkToken()) {
            self::generateToken();
        }

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, self::vvEndpoint() . "users/create-user");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . self::$vv_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch,CURLOPT_POST, count(self::$parameters));
        curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode(self::$parameters));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $result = curl_exec($ch);
        
        $json_result = json_decode($result);

        self::$parameters = null;
        if ($json_result->status == 400) {
            return false;
        } 
        else {
            $vv_user = [
                'password' => $password,
                'existing_user' => $existing_user
            ];
            return $vv_user;
        }
	}

    public static function getStates() 
    {
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, self::vvEndpoint() . "common/get-states" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);
        $states = json_decode($result);
        $statesList = [];
        foreach($states->data as $key => $state) {
            $statesList[$state->abbreviation] = $state->value;
        }
        self::$states_list = $statesList;
        return $statesList;
    }
}

?>