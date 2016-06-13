<?php

/**
 * Allows for connection to Tradable Bits service.
 *
 * This class was adapted from: https://github.com/cosenary/Instagram-PHP-API
 *
 * API Documentation: http://tradablebits.com/developers
 * 
 * @since 2016.05.02
 * @version 1.0
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 */

class TradableBits {
    /**
     * The API base URL.
     */
    const API_URL = 'https://tradablebits.com/api/v1/';

    /**
     * The API OAuth URL.
     */
    const API_OAUTH_URL = 'https://tradablebits.com/crm/oauth';

    /**
     * The OAuth token URL.
     */
    const API_OAUTH_TOKEN_URL = 'https://tradablebits.com/crm/access_token';

    /**
     * The default amount of media to pull per fetch.
     * Tradable Bits API states a limit of 100 as of 2016/05/16.
     */
    const MEDIA_LIMIT_DEFAULT = 100;

    /**
     * The Tradable Bits API Key.
     *
     * @var string
     */
    private $_apikey;

    /**
     * The Tradable Bits OAuth API secret.
     *
     * @var string
     */
    private $_apisecret;

    /**
     * The callback URL.
     *
     * @var string
     */
    private $_callbackurl;

    /**
     * The user access token.
     *
     * @var string
     */
    private $_accesstoken;

    /**
     * The Tradable Bits account ID.
     *
     * @var string
     */
    private $_accountid;

    /**
     * The Tradable Bits stream key we are working with.
     *
     * @var string
     */
    private $_streamkey;

    /**
     * Whether a signed header should be used.
     *
     * @var bool
     */
    private $_signedheader = false;

    /**
     * Default constructor
     *
     * @param array|string $config Tradable Bits configuration data
     * @return void
     *
     * @throws Exception
     */
    public function __construct($config) {
        if (true === is_array($config)) {
            // if you want to access user data
            $this->setApiKey($config['apiKey']);
            $this->setApiSecret($config['apiSecret']);
            $this->setApiCallback($config['apiCallback']);
            $this->setAccountId($config['accountId']);
            $this->setStreamKey($config['streamKey']);
        } else if (true === is_string($config)) {
            // if you only want to access public data
            $this->setApiKey($config);
        } else {
            throw new Exception("Error: __construct() - Configuration data is missing.");
        }
    }

    /**
     * Get a stream's recent media. Can only set maxTimeKey OR minTimeKey. Not both.
     * 
     * Docs: http://tradablebits.com/developers#api-stream (/api/v1/streams/[ STREAM_KEY ]/records)
     *
     * @param int    $maxTimeKey Maximum age of media to fetch.
     * @param int    $minTimeKey Minimum age of media to fetch.
     * @param int    $limit      Maximum number of results to return.
     * @param string $streamKey  The stream's key
     *
     * @return mixed
     */
    public function getStreamMedia($maxTimeKey = null, $minTimeKey = null, $limit = self::MEDIA_LIMIT_DEFAULT, $streamKey = null)
    {
        $params = array();

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        // Cannot specify both min and max; only one of the two.
        if ($maxTimeKey && $minTimeKey) {
            throw new Exception("Error: getStreamMedia() - This function requires only one of 'maxTimeKey', 'minTimeKey' be specified.");
        } else if ($maxTimeKey) {
            $params['max_time_key'] = $maxTimeKey;
        } else if ($minTimeKey) {
            $params['min_time_key'] = $minTimeKey;
        }

        if (!$streamKey) {
            $streamKey = $this->getStreamKey();
        }

        return $this->_makeCall('streams/' . $streamKey . '/records', false, $params);
    }

    /**
     * Get an account's status.
     * 
     * Docs: http://tradablebits.com/developers#api-misc (/api/v1/status)
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->_makeCall('status');
    }

    /**
     * Get the OAuth data of a user by the returned callback code.
     *
     * @param string $code OAuth2 code variable (after a successful login)
     * @param bool $token If it's true, only the access token will be returned
     *
     * @return mixed
     */
    public function getOAuthToken($code, $token = false)
    {
        $apiData = array(
            'code' => $code,
            'api_key' => $this->getApiSecret(),
            'account_id' => $this->getAccountId(),
            'redirect_url' => $this->getApiCallback(),
            'grant_type' => 'authorization_code'
        );

        $result = $this->_makeOAuthCall($apiData);

        return !$token ? $result : $result->access_token;
    }

    /**
     * The call operator.
     *
     * @param string $function API resource path
     * @param bool $auth Whether the function requires an access token
     * @param array $params Additional request parameters
     * @param string $method Request type GET|POST
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function _makeCall($function, $auth = false, $params = null, $method = 'GET')
    {
        if (!$auth) {
            // if the call doesn't require authentication
            $authMethod = '?api_key=' . $this->getApiKey();
        } else {
            // if the call needs an authenticated user
            if (!isset($this->_accesstoken)) {
                throw new Exception("Error: _makeCall() | $function - This method requires an authenticated users access token.");
            }

            $authMethod = '?access_token=' . $this->getAccessToken();
        }

        if ($params != null) {
            // add timestamp for cache busting
            $params['timestamp'] = time();
        }

        $paramString = null;

        if (isset($params) && is_array($params)) {
            $paramString = '&' . http_build_query($params);
        }

        $apiCall = self::API_URL . $function . $authMethod . (('GET' === $method) ? $paramString : null);

        // we want JSON
        $headerData = array('Accept: application/json');

        if ($this->_signedheader) {
            $apiCall .= (strstr($apiCall, '?') ? '&' : '?') . 'sig=' . $this->_signHeader($function, $authMethod, $params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiCall);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, count($params));
                curl_setopt($ch, CURLOPT_POSTFIELDS, ltrim($paramString, '&'));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $jsonData = curl_exec($ch);
        // split header from JSON data
        // and assign each to a variable
        list($headerContent, $jsonData) = explode("\r\n\r\n", $jsonData, 2);

        if (!$jsonData) {
            throw new Exception('Error: _makeCall() - cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($jsonData, true);
    }

    /**
     * The OAuth call operator.
     *
     * @param array $apiData The post API data
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function _makeOAuthCall($apiData)
    {
        $apiHost = self::API_OAUTH_TOKEN_URL;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiHost);
        curl_setopt($ch, CURLOPT_POST, count($apiData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $jsonData = curl_exec($ch);
        var_dump(curl_getinfo($ch, CURLINFO_HEADER_OUT));
        var_dump(http_build_query($apiData));
        var_dump($jsonData);


        if (!$jsonData) {
            throw new Exception('Error: _makeOAuthCall() - cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($jsonData);
    }

    /**
     * Sign header by using endpoint, parameters and the API secret.
     *
     * @param string
     * @param string
     * @param array
     *
     * @return string The signature
     */
    private function _signHeader($endpoint, $authMethod, $params)
    {
        if (!is_array($params)) {
            $params = array();
        }
        if ($authMethod) {
            list($key, $value) = explode('=', substr($authMethod, 1), 2);
            $params[$key] = $value;
        }
        $baseString = '/' . $endpoint;
        ksort($params);
        foreach ($params as $key => $value) {
            $baseString .= '|' . $key . '=' . $value;
        }
        $signature = hash_hmac('sha256', $baseString, $this->_apisecret, false);

        return $signature;
    }

    /**
     * Access Token Setter.
     *
     * @param object|string $data
     *
     * @return void
     */
    public function setAccessToken($data)
    {
        $token = is_object($data) ? $data->access_token : $data;

        $this->_accesstoken = $token;
    }

    /**
     * Access Token Getter.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_accesstoken;
    }

    /**
     * Account ID setter.
     *
     * @param string $accountId
     *
     * @return void
     */
    public function setAccountId($accountId)
    {
        $this->_accountid = $accountId;
    }

    /**
     * Account ID getter.
     *
     * @return string
     */
    public function getAccountId()
    {
        return $this->_accountid;
    }

    /**
     * API-key Setter
     *
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->_apikey = $apiKey;
    }

    /**
     * API Key Getter
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->_apikey;
    }

    /**
     * API Secret Setter
     *
     * @param string $apiSecret
     *
     * @return void
     */
    public function setApiSecret($apiSecret)
    {
        $this->_apisecret = $apiSecret;
    }

    /**
     * API Secret Getter.
     *
     * @return string
     */
    public function getApiSecret()
    {
        return $this->_apisecret;
    }

    /**
     * API Callback URL Setter.
     *
     * @param string $apiCallback
     *
     * @return void
     */
    public function setApiCallback($apiCallback)
    {
        $this->_callbackurl = $apiCallback;
    }

    /**
     * API Callback URL Getter.
     *
     * @return string
     */
    public function getApiCallback()
    {
        return $this->_callbackurl;
    }

    /**
     * Enforce Signed Header.
     *
     * @param bool $signedHeader
     *
     * @return void
     */
    public function setSignedHeader($signedHeader)
    {
        $this->_signedheader = $signedHeader;
    }

    /**
     * Stream Key setter.
     *
     * @param string $streamKey
     *
     * @return void
     */
    public function setStreamKey($streamKey)
    {
        $this->_streamkey = $streamKey;
    }

    /**
     * Stream Key getter.
     *
     * @return string
     */
    public function getStreamKey()
    {
        return $this->_streamkey;
    }
}
