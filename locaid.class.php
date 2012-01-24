<?php

/**
 * Common Loc-Aid functions
 *
 * @author max@neuropunks.org
 * @package locaid
 * 
 */
class Locaid {
    // @todo most calls support multiple mobiles - batching
    // @todo we can do async requests using transaction_id

    const DEBUG = true;

    const WSDL_REGISTRATION = 'https://ws.loc-aid.net/webservice/RegistrationServices?wsdl';
    const WSDL_GETXY = 'https://ws.loc-aid.net/webservice/LatitudeLongitudeServices?wsdl';
    const WSDL_ADDRESS = '';
    const WSLD_GETXYADDRESS = '';
    const WSDL_NUMBERING = '';

    const ERROR_OBJECT_GENERIC = 'error';
    const ERROR_OBJECT_MSISDN = 'msisdnError';

    public $request_last_errorType;
    public $request_last_errorCode;
    public $request_last_errorMessage;

    protected $locaid_username;
    protected $locaid_password;
    protected $locaid_classid;
    
    protected $mobile;

    protected $wsdl_current;

    protected $request;
    protected $response;

    private $registration_api;
    private $getxy_api;

    /**
     *
     * @param string $username
     * @param string $password
     * @param string $classid
     * @param string $mobile_num - must be in MSISDN format
     */
    public function __construct($username, $password, $classid, $mobile_num) {
        $this->soap_options = array(
            'trace' => 1,
            'exceptions' => 1,
        );

        $this->locaid_username = $username;
        $this->locaid_password = $password;
        $this->locaid_classid = $classid;
        $this->setMobile($mobile_num);        
    }

    /**
     * Get the Registration API
     * @return Locaid_API_Registration
     */
    public final function ReistrationApi() {
        if (!$this->registration_api) {
            $this->registration_api = new Locaid_API_Registration($this->locaid_username, $this->locaid_password, $this->locaid_classid, $this->mobile);
        }

        return $this->registration_api;
    }

    /**
     * Get the GetXY API
     * @return Locaid_API_GetXY
     */
    public final function GetXYApi() {
        if (!$this->getxy_api) {
            $this->getxy_api = new Locaid_API_GetXY($this->locaid_username, $this->locaid_password, $this->locaid_classid, $this->mobile);
        }

        return $this->getxy_api;
    }

    /**
     *
     * @return string
     */
    public final function getMobile() {
        return $this->mobile;
    }

    /**
     *
     * @param string $mobile - MSISDN format
     */
    public final function setMobile($mobile) {
        if (!$this->isMSISDN($mobile)) {
            throw new Exception(__METHOD__ . " $mobile is not in MSISDN format (CC+NPA+SN)");
        }

        $this->mobile = $mobile;
    }

    /**
     * See if the phone is in MSISDN format
     * @param string $mobile
     * @return bool
     */
    public final function isMSISDN($mobile) {
        if (!preg_match('/^[1-9][0-9]{10,14}$/', $mobile)) {
            return false;
        }
        return true;
    }

    /**
     * Information about the last request
     * @return stdClass
     */
    protected function getLastRequest() {
        $ret = new stdClass();
        $ret->wsdl = $this->wsdl_current;
        $ret->api = $this->request_last_api;
        $ret->function  = $this->request_last_function;
        $ret->request = $this->request;
        return $ret;
    }

    /**
     * Information about the last response
     * @return stdClass
     */
    protected function getLastResponse() {
        $ret = new stdClass();
        $ret->wsdl = $this->wsdl_current;
        $ret->api = $this->request_last_api;
        $ret->function  = $this->request_last_function;
        $ret->response = $this->response;
        return $ret;
    }

    /**
     * Call a specific SOAP function
     * @param string $function
     */
    protected function __request($function) {
        if (!class_exists('SoapClient')) {
            throw new Exception(__METHOD__ . ': SoapClient is not installed');
        }
        
        if (!$this->request) {
            throw new Exception (__METHOD__ . ': Request object is not defined');
        }

        if (!($this->locaid_username && $this->locaid_password && $this->locaid_classid)) {
            throw new Exception(__METHOD__ . ': Need both Loc-AID username, password and classId are required');
        }
        
        if (!$this->getMobile()) {
            throw new Exception(__METHOD__ . ': Need mobile');
        }

        if (!$this->wsdl_current) {
            throw new Exception(__METHOD__ . ': Unknown functon call');
        }

        // set auth
        $this->request->login = $this->locaid_username;
        $this->request->password = $this->locaid_password;

        $this->request_last_api = get_class($this);
        $this->request_last_function = $function;

        $soap = new SoapClient($this->wsdl_current,$this->soap_options);

        try {
            $ret = $soap->$function($this->request);
            $this->response = $ret->return;

            if (self::DEBUG) {
                $this->__debug();
            }

            if($this->response->error instanceof stdClass) {
                $this->request_last_errorType = self::ERROR_OBJECT_GENERIC;
                $this->request_last_errorCode = $this->response->error->errorCode;
                $this->request_last_errorMessage = $this->response->error->errorMessage;
            }

            if($this->response->msisdnError instanceof stdClass) {
                $this->request_last_errorType = self::ERROR_OBJECT_MSISDN;
                $this->request_last_errorCode = $this->response->msisdnError->errorCode;
                $this->request_last_errorMessage = $this->response->msisdnError->errorMessage;
            }

            if ($this->request_last_errorCode) {
                throw new Exception(__METHOD__ . ": {$this->request_last_api}::{$this->request_last_function}::{$this->request_last_errorType}::{$this->request_last_errorCode}::{$this->request_last_errorMessage}");
            }
            
        } catch (SoapFault $e) {
            throw new Exception(__METHOD__ . ': Got SOAP fault: ' . $e->getMessage());
        }
    }

    /**
     * do what you will...
     */
    public function __debug() {
        var_dump($this->getLastRequest(), $this->getLastResponse());
    }
}

/**
 * Registration API
 *
 * @author max@neuropunks.org
 * @package locaid
 * @subpackage api-wrapper
 */
class Locaid_API_Registration extends Locaid {

    const COMMAND_YES = 'YES';
    const COMMAND_Y = 'Y';
    const COMMAND_NO = 'NO';
    const COMMAND_N = 'N';
    const COMMAND_OPTIN = 'OPTIN';
    const COMMAND_CANCEL = 'CANCEL';
    const COMMAND_LOCK = 'LOCK';
    const COMMAND_UNLOCK = 'UNLOCK';
    const COMMAND_HELP = 'HELP';
    const COMMAND_ALL = 'ALL';

    const MSG_OPTIN_COMPLETE = 'OPTIN_COMPLETE';

    
    /**
     *
     * @param string $username
     * @param string $password
     * @param string $classid
     * @param string $mobile_num - must be in MSISDN format
     */
    public function  __construct($username, $password, $classid, $mobile_num) {
        parent::__construct($username, $password, $classid, $mobile_num);
        $this->wsdl_current = self::WSDL_REGISTRATION;        
    }

    /**
     * Call registration function and get back a status string
     * @return stdClass
     */
    public function Register() {
        $this->request = new stdClass();
        $this->request->command = self::COMMAND_OPTIN;
        $this->request->classIdList = new stdClass();
        $this->request->classIdList->classId = $this->locaid_classid;
        $this->request->classIdList->msisdnList = $this->mobile;
        
        $this->__request('subscribePhone');

        $response = new stdClass();
        $response->status = $this->response->classIdList->msisdnList->status;

        return $response;
    }

    /**
     *
     * @return stdClass
     */
    public function Unregister() {
        $this->request = new stdClass();
        $this->request->command = self::COMMAND_CANCEL;
        $this->request->classIdList = new stdClass();
        $this->request->classIdList->classId = $this->locaid_classid;
        $this->request->classIdList->msisdnList = $this->mobile;
        
        $this->__request('subscribePhone');

        $response = new stdClass();
        return $response;
    }

    /**
     *
     * @return stdClass
     */
    public function LockLocationPerm() {
        $this->request = new stdClass();
        $this->request->command = self::COMMAND_LOCK;
        $this->request->classIdList = new stdClass();
        $this->request->classIdList->classId = $this->locaid_classid;
        $this->request->classIdList->msisdnList = $this->mobile;

        $this->__request('subscribePhone');

        $response = new stdClass();
        return $response;
    }

    /**
     *
     * @return stdClass 
     */
    public function UnlockLocationPerm(){
        $this->request = new stdClass();
        $this->request->command = self::COMMAND_UNLOCK;
        $this->request->classIdList = new stdClass();
        $this->request->classIdList->classId = $this->locaid_classid;
        $this->request->classIdList->msisdnList = $this->mobile;

        $this->__request('subscribePhone');
        
        $response = new stdClass();
        return $response;
    }

    /**
     * See if this mobile is already opted in
     * @return bool
     */
    public function getIsOptedIn() {
        $this->request = new stdClass();
        $this->request->msisdnList = $this->mobile;

        $this->__request('getPhoneStatus');

        if ($this->response->msisdnList->classIdList->status == self::MSG_OPTIN_COMPLETE) {
            return true;
        }
        return false;
    }
}

/**
 * GetXY API
 *
 * @author max@neuropunks.org
 * @package locaid
 * @subpackage api-wrapper
 */
class Locaid_API_GetXY extends Locaid {

    const COOR_DECIMAL = 'DECIMAL';
    const COOR_DMS = 'DMS';

    const LOC_LEAST_EXPENSIVE = 'LEAST_EXPENSIVE';
    const LOC_MOST_ACCURATE = 'MOST_ACCURATE';
    const LOC_CELL = 'CELL';
    const LOC_A_GPS = 'A-GPS';

    const SYNC_SYN = 'SYN';
    const SYNC_ASYNC = 'ASYNC';

    private $coor_type = self::COOR_DECIMAL;
    private $location_method = self::LOC_LEAST_EXPENSIVE;
    private $sync_type = self::SYNC_SYN;
    private $overage = 1;

    /**
     *
     * @param string $username
     * @param string $password
     * @param string $classid
     * @param string $mobile_num
     */
    public function  __construct($username, $password, $classid, $mobile_num) {
        parent::__construct($username, $password, $classid, $mobile_num);
        $this->wsdl_current = self::WSDL_GETXY;

    }

    /**
     *
     * @return string
     */
    public function getCoorType() {
        return $this->coor_type;
    }

    /**
     *
     * @param string $type
     */
    public function setCoorType($type) {
        if ($type != self::COOR_DECIMAL || $type != self::COOR_DMS) {
            throw new Exception(__METHOD__ . ": Unknown coordinates type $type");
        }
        $this->coor_type = $type;
    }

    /**
     *
     * @return string
     */
    public function getLocationMethod() {
        return $this->location_method;
    }

    /**
     *
     * @param string $type
     */
    public function setLocationMethod($type) {
        if ($type != self::LOC_LEAST_EXPENSIVE || $type != self::LOC_MOST_ACCURATE || $type != self::LOC_CELL || $type != self::LOC_A_GPS) {
            throw new Exception(__METHOD__ . ": Unknown location method $type");
        }
        $this->coor_type = $type;
    }

    /**
     *
     * @return string
     */
    public function getSyncType() {
        return $this->sync_type;
    }

    /**
     *
     * @param string $type
     */
    public function setSyncType($type) {
        if ($type != self::SYNC_SYN || $type != self::SYNC_ASYNC) {
            throw new Exception(__METHOD__ . ": Unknown sync type $type");
        }
        $this->coor_type = $type;
    }

    /**
     * Returns X, Y, technology used and geometry for a mobile location
     * @return StdClass
     */
    public function getLocation() {
        $this->request = new stdClass();
        $this->request->classId = $this->locaid_classid;
        $this->request->msisdnList = $this->mobile;
        $this->request->coorType = $this->coor_type;
        $this->request->locationMethod = $this->location_method;
        $this->request->syncType = $this->sync_type;
        $this->request->overage = $this->overage;

        $this->__request('getLocationsX');

        $response = new StdClass();
        $response->status = $this->response->locationResponse->status;
        $response->technology = $this->response->locationResponse->technology;
        $response->X = $this->response->locationResponse->coordinateGeo->x;
        $response->Y = $this->response->locationResponse->coordinateGeo->y;
        $response->geometry = $this->response->locationResponse->geometry;
        
        // returned time is always in UTC
        // example: 20120124201056 -> 2012/01/24 20:10:56 UTC
        $time = $this->response->locationResponse->locationTime->time;
        $t = DateTime::createFromFormat('YmdHis', $time, new DateTimeZone('UTC'));
        $t->setTimeZone(new DateTimeZone(date('e')));
        $response->datetime = $t->format('Y-m-d H:i:s');
        $response->timestamp = $t->getTimestamp();

        return $response;
    }

    
}
