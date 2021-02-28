<?php
    /**
     * class Impftermine
     * Please use this PHP class at your own RESPONSIBILITY and RISK. 
     * 
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * @copyright   (C) 2020-2021 Rechenzentrum Fuhrmann Inh. Sebastian Fuhrmann
     * 
     * Roadmap:
     * - add caching
     */
    class Impftermine {
        public function __construct(){
            
        }

        private function doRequest($url){
            $ch = curl_init(); 
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $raw = curl_exec($ch); 
            $info = curl_getinfo($ch);
            curl_close($ch); 

            if ($info["http_code"] == 200 && ($json = json_decode($raw, true))){
                return $json; 
            }
        }

        /**
         * getVaccines()
         * returns a list of available vaccines
         * Endpoint: https://www.impfterminservice.de/assets/static/its/vaccination-list.json
         */
        public function getVaccines (){
            $list = $this->doRequest('https://www.impfterminservice.de/assets/static/its/vaccination-list.json');
            return $list;
        }

        /**
         * getVaccinationCenters()
         * returns a list of available and active vaccination centers
         * Endpoint: https://www.impfterminservice.de/assets/static/impfzentren.json
         */
        public function getVaccinationCenters(){
            $list = $this->doRequest('https://www.impfterminservice.de/assets/static/impfzentren.json');
            return $list; 
        }
    }
    
?>