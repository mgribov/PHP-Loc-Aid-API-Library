<?php
require_once 'locaid.class.php';

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
    const MSG_OPTIN_PENDING = 'OPTIN_PENDING';
    const MSG_OK = 'OK';

    const ERROR_SUBSCRIPTION_PENDING = '00018';

    
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
        $response->error = null;
        
        // we may be waiting for a subscription confirmation or some other error has occurred
        if ($this->response->classIdList->msisdnList->error instanceof stdClass) {
            $response->status = $this->response->classIdList->msisdnList->error->errorMessage;
            $response->error = $this->response->classIdList->msisdnList->error->errorCode;
        } else {
            $response->status = $this->response->classIdList->msisdnList->status;
        }
        
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
        $response->status = $this->response->classIdList->msisdnList->status;
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
     * Get the phone status
     * @return bool
     */
    public function getStatus() {
        $this->request = new stdClass();
        $this->request->msisdnList = $this->mobile;

        $this->__request('getPhoneStatus');

        $response = new stdClass();
        $response->status = $this->response->msisdnList->classIdList->status;
        return $response;
    }

    /**
     * See if this mobile is already opted in
     * @return bool
     */
    public function getIsOptedIn() {
        $status = $this->getStatus();

        if ($status->status == self::MSG_OPTIN_COMPLETE) {
            return true;
        }
        return false;
    }
}

