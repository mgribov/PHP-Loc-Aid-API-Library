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
