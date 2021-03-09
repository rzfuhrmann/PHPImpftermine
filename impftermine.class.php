<?php
    /**
     * class Impftermine
     * Please use this PHP class at your own RESPONSIBILITY and RISK. 
     * 
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * @copyright   (C) 2020-2021 Rechenzentrum Fuhrmann Inh. Sebastian Fuhrmann
     * @version     2021-03-09
     * @license     MIT
     * 
     * Roadmap:
     * - add/improve caching
     * - clean-up code
     * - add examples and helper functions for looping through vaccination centers and vaccines
     * - add logging
     * - fix naming, don't just pass the property names from web service
     * - add debug mode
     * - getAvailbilityByVaccinationCenterAndVaccine()?
     * - throw Exceptions
     */

    class Impftermine {
        private $cachingDir = __DIR__.'/cache/';

        private $endpointStatic = 'https://www.impfterminservice.de/assets/static/';

        private $appname = null; 

        public function __construct($appname){
            if (!$appname || !is_string($appname) || strlen($appname) < 10 || $appname == "my-unique-appname"){
                throw new Exception("Please set a unique App name in the constructor.");
            }
            $this->appname = $appname; 
            
        }

        public function setCachingDir ($path){
            if (file_exists($path)){
                $this->cachingDir = $path.'/'; 
            }
        }

        private function isCachingEnabled(){
            return !!file_exists($this->cachingDir); 
        }

        private function doRequest($url, $cfg = array("cachingTime" => 1)){
            $res = null; 

            // write cache file, but don't re-use it long
            if (!isset($cfg["cachingTime"])) $cfg["cachingTime"] = 1; 

            $cache_fn = $this->cachingDir.md5($url).".json";
            if ($this->isCachingEnabled() && file_exists($cache_fn) && time() < filemtime($cache_fn)+$cfg["cachingTime"]){
                // @todo: Error handling
                $res = json_decode(file_get_contents($cache_fn), true); 
            } else {
                $ch = curl_init(); 

                // 2021-03-09: Break cache since someone forgot to purge Akamai's cache so that impfterminservice.de responds with different JSONs randomly... 
                curl_setopt($ch, CURLOPT_URL, $url.'?rrnd='.(time().rand(100000000,999999999999)));

                // 2021-03-09: increasing timeout since *.impfterminservice.de takes ~5 min so send a response... 
                // 5 mins
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5*60); 
                // 5 mins
                curl_setopt($ch, CURLOPT_TIMEOUT, 5*60); 

                curl_setopt($ch, CURLOPT_USERAGENT, $this->appname . ' - using https://github.com/rzfuhrmann/PHPImpftermine');

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $raw = curl_exec($ch); 
                $info = curl_getinfo($ch);
                curl_close($ch); 

                if ($info["http_code"] == 200 && ($json = json_decode($raw, true))){
                    $res = $json; 
                    if ($this->isCachingEnabled()){
                        file_put_contents($cache_fn, json_encode($json, JSON_PRETTY_PRINT));
                    }
                }
            }
            
            return $res; 
        }

        /**
         * getVaccines()
         * returns a list of available vaccines
         * Endpoint: https://www.impfterminservice.de/assets/static/its/vaccination-list.json
         */
        public function getVaccines (){
            $list = $this->doRequest(
                        //$this->endpointStatic.'its/vaccination-list.json', 
                        // 2021-03-09: switching to 001-iz since impfterminservice seems to be broken... 
                        'https://001-iz.impfterminservice.de/assets/static/its/vaccination-list.json',
                        array(
                            "cachingTime" => 60*60*24     // will not change that often ;)
                        )
                    );
            return $list;
        }

        /**
         * getVaccinationCenters()
         * returns a list of available and active vaccination centers
         * Endpoint: https://www.impfterminservice.de/assets/static/impfzentren.json
         */
        public function getVaccinationCenters(){
            $list = $this->doRequest(
                        $this->endpointStatic.'impfzentren.json',
                        array(
                            "cachingTime" => 60*60*24     // will not change that often ;)
                        )
                    );
            return $list; 
        }

        public function getVaccinationCenter($countrycode){
            $centerList = $this->getVaccinationCenters(); 
            foreach ($centerList as $state => $centers){
                foreach ($centers as $center){
                    if ($center["PLZ"] == $countrycode){
                        return $center; 
                    }
                }
            }
            return false; 
        }

        public function getAvailbilityByVaccinationCenter($countrycode){
            // we need the specific base URL of that center
            $center = $this->getVaccinationCenter($countrycode);
            if (!$center){
                // Exception?
                return false; 
            }
            $res = array(
                "center" => $center,
                "vaccines" => []
            );

            // https://LOCAL_URL/rest/suche/termincheck?plz=PLZ&leistungsmerkmale=L920,L921,L922
            $vaccines = $this->getVaccines(); 
            foreach ($vaccines as $vaccine){
                $avail = $this->doRequest(
                            $center["URL"].'rest/suche/termincheck?plz='.$center["PLZ"].'&leistungsmerkmale='.implode(",", array($vaccine["qualification"])),
                            array(
                                "cachingTime" => 60*2
                            )
                         );
                $vaccine["available"] = $avail["termineVorhanden"]?true:false;
                $res["vaccines"][] = $vaccine;
            }

            return $res; 
        }

        /**
         * getAvailbilityMatrix()
         * get appointment availbility for all vaccination centers
         * PLEASE NOTE: This function will cause a lot (number of active centers * number of available vaccines) request to various impfterminservice.de-Endpoints!
         * 
         */
        public function getAvailbilityMatrix(){
            $centerList = $this->getVaccinationCenters(); 
            $res = array(); 
            foreach ($centerList as $state => $centers){
                foreach ($centers as $center){
                    $centerAvailibity = $this->getAvailbilityByVaccinationCenter($center["PLZ"]);
                    $center["state"] = $state; 
                    $center["vaccines"] = $centerAvailibity["vaccines"]; 
                    $res[] = $center; 
                }
            }
            return $res; 
        }

        // https://001-iz.impfterminservice.de/rest/suche/terminpaare?plz={{PLZ}}
        // {"gesuchteLeistungsmerkmale":["L922"],"terminpaare":[],"praxen":{}}
        // Unsure, if this already reserves appointments. Therefore not implemented yet/not published to GitHub. 
        public function getAppointmentPairs(){
            return false;
        }
    }
    
?>