| :warning: **Please note**: Sending requests to impfterminservice.de can cause outages and unavailability and should avoided if possible. Therefore, use that PoC very responsible and fairly to others. Thank you. |
|----|

Proof of Concept (PoC) - so mostly incomplete and untested code - how a PHP wrapper for Impfterminservice could look like.

```PHP
require_once '/var/www/rz/PHPImpftermine/impftermine.class.php';

// replace "my-unique-appname" with an unique name for your application
$impftermine = new Impftermine("my-unique-appname");

// get available vaccines
$res = $impftermine->getVaccines(); 

// get vaccination centers
$res = $impftermine->getVaccinationCenters(); 

// get vaccination center details
$res = $impftermine->getVaccinationCenter(PLZ); 

// get appointment availibility by vaccination center (= PLZ)
$res = $impftermine->getAvailbilityByVaccinationCenter(PLZ);
```