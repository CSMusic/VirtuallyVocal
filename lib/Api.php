<?php

namespace VirtuallyVocal;


class Api {
    public static $test;
    public static $client_id;
	public static $client_secret;
    public static $vv_token;
    public static $token_timestamp;
    public static $vv_endpoint;


    public static function setTestMode($test)
	{
		self::$test = $test;
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
        $parameters = Array();
        $parameters["clientId"] = self::$client_id;
        $parameters["secret"] = self::$client_secret;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::vvEndpoint() . "auth/generate-token" );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, count($parameters) );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters) );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        $result = curl_exec($ch);
        $decoded = json_decode($result);

        if($decoded->data != null) {
            self::$vv_token = $decoded->data;
        }
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
        $states_list = [
            "AL" => 1,
            "AK" => 2,
            "AZ" => 3,
            "AR" => 4,
            "CA" => 5,
            "CO" => 6,
            "CT" => 7,
            "DE" => 8,
            "FL" => 9,
            "GA" => 10,
            "HI" => 11,
            "ID" => 12,
            "IN" => 14,
            "IA" => 15,
            "KS" => 16,
            "KY" => 17,
            "LA" => 18,
            "ME" => 19,
            "MD" => 20,
            "MA" => 21,
            "MI" => 22,
            "MN" => 23,
            "MS" => 24,
            "MO" => 25,
            "MT" => 26,
            "NE" => 27,
            "NV" => 28,
            "NH" => 29,
            "NJ" => 30,
            "NM" => 31,
            "NY" => 32,
            "NC" => 33,
            "ND" => 34,
            "OH" => 35,
            "OK" => 36,
            "OR" => 37,
            "PA" => 38,
            "RI" => 39,
            "SC" => 40,
            "SD" => 41,
            "TN" => 42,
            "TX" => 43,
            "UT" => 44,
            "VT" => 45,
            "VA" => 46,
            "WA" => 47,
            "WV" => 48,
            "WI" => 49,
            "WY" => 50,
            "DC" => 51,
            "Other" => 52
            ];
        if($state == "") {
            return 52; // Value for Other. Don't bother with states list.
        }
        if(array_key_exists($state, $states_list)) {
            return $states_list[$state];
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
        $parameters = Array();

        // these fields are required by Virtually Vocal, so set if not found on existing user or on account.
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
        $parameters['firstName'] = $Account->first_name;
        $parameters['lastName'] = $Account->last_name;
        $parameters['username'] = $Account->email;
        $password = self::generatePassword();
        $parameters['password'] = $password;
        $parameters['address1'] = $Account->_profile_address_street;
        $parameters['address2'] = $Account->_profile_address_apt;
        $parameters['city'] = $Account->_profile_address_city;
        $parameters['stateId'] = self::getStateValue($Account->_profile_address_state);
        $parameters['zipcode'] = $Account->_profile_address_postalcode;
        $parameters['email'] = $Account->email;
        $parameters['rbSubscriptionId'] = "00000";
        $parameters['source'] = "CSMUSIC";
        $parameters['role'] = $role;
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
        curl_setopt($ch,CURLOPT_POST, count($parameters));
        curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        $result = curl_exec($ch);
        $json_result = json_decode($result);
        if($json_result->result == "Success") {
            return $password;
        }
        else {
            return false;
        }
	}
}

?>