<?php
require_once 'locaid.class.php';

try {
    $mobile = new Locaid($username, $password, $classid, $mobile_num);

    if ($mobile->RegistrationApi()->getIsOptedIn()) {

        // lat/long
        $res = $mobile->GetXYApi()->getLocation();

        // show the coords:
        echo "found $mobile_num at {$res->Y}, {$res->X} using {$res->technology} technology on {$res->datetime} (unix time: {$res->timestamp})\n";

        // lets unregister this guy so we can demo registration next run
        echo "going to unregister $mobile_num...";

        $res = $mobile->RegistrationApi()->Unregister();

        echo $res->status . ' ' . $res->error . "\n";


    } else {

        // request registration via SMS message
        echo "$mobile_num was not registered, sending registration SMS message: ";

        $res = $mobile->RegistrationApi()->Register();

        echo $res->status . ' ' . $res->error . "\n";

    }

} catch (Exception $e) {
    echo "Cannot complete request: {$e->getMessage()}";
}

?>

