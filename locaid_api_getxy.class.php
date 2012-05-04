<?php
require_once 'locaid.class.php';

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

    const MSG_NOTFOUND = 'NOT_FOUND';

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
        
        if ($response->status = self::MSG_NOTFOUND) {
            $response->message = 'not found';
            $time = date('YmdHis');
            $tz = date('e');
        } else {
            $response->technology = $this->response->locationResponse->technology;
            $response->X = $this->response->locationResponse->coordinateGeo->x;
            $response->Y = $this->response->locationResponse->coordinateGeo->y;
            $response->geometry = $this->response->locationResponse->geometry;
            
            // returned time is always in UTC
            // example: 20120124201056 -> 2012/01/24 20:10:56 UTC
            $time = $this->response->locationResponse->locationTime->time;
            $tz = 'UTC';
        }

        $t = DateTime::createFromFormat('YmdHis', $time, new DateTimeZone($tz));
        $t->setTimeZone(new DateTimeZone(date('e')));
        $response->datetime = $t->format('Y-m-d H:i:s');
        $response->timestamp = $t->getTimestamp();
    
        return $response;
    }
}

